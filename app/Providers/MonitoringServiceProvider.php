<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventAlertDispatcher;
use App\Application\Monitoring\DomainEventProjector;
use App\Application\Monitoring\DomainEventService;
use App\Application\Monitoring\HealthCheckService;
use App\Application\Monitoring\Metrics\MetricsRecorder;
use App\Application\Monitoring\Metrics\NullMetricsRecorder;
use App\Application\Monitoring\Projectors\DispatchEventProjector;
use App\Application\Monitoring\Projectors\NotificationEventProjector;
use App\Application\Monitoring\Projectors\OrderEventProjector;
use App\Application\Monitoring\Projectors\ShipmentEventProjector;
use App\Application\Monitoring\Queries\ListAuditLogs;
use App\Application\Monitoring\Queries\ListDomainEvents;
use App\Application\Monitoring\Queries\ListSystemJobs;
use App\Application\Monitoring\SystemJobAlertService;
use App\Application\Monitoring\SystemJobFailureStreakService;
use App\Application\Monitoring\SystemJobLifecycleService;
use App\Application\Monitoring\SystemJobPolicyService;
use App\Application\Monitoring\SystemJobRetryService;
use App\Application\Monitoring\SystemJobTrackingCoordinator;
use App\Application\Tracking\TrackingAlertService;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Monitoring\Contracts\AuditLogRepository;
use App\Domain\Monitoring\Contracts\DatabaseHealthProbe;
use App\Domain\Monitoring\Contracts\DispatchEventReportRepository;
use App\Domain\Monitoring\Contracts\DomainEventRepository;
use App\Domain\Monitoring\Contracts\NotificationEventReportRepository;
use App\Domain\Monitoring\Contracts\OrderEventReportRepository;
use App\Domain\Monitoring\Contracts\ShipmentEventReportRepository;
use App\Domain\Monitoring\Contracts\SystemJobRepository;
use App\Events\DomainEventRecorded;
use App\Infrastructure\Monitoring\DatabaseConnectionHealthProbe;
use App\Infrastructure\Monitoring\Metrics\StatsDMetricsRecorder;
use App\Infrastructure\Persistence\Monitoring\Eloquent\EloquentAuditLogRepository;
use App\Infrastructure\Persistence\Monitoring\Eloquent\EloquentDomainEventRepository;
use App\Infrastructure\Persistence\Monitoring\Eloquent\EloquentSystemJobRepository;
use App\Infrastructure\Persistence\Reporting\DatabaseDispatchEventReportRepository;
use App\Infrastructure\Persistence\Reporting\DatabaseNotificationEventReportRepository;
use App\Infrastructure\Persistence\Reporting\DatabaseOrderEventReportRepository;
use App\Infrastructure\Persistence\Reporting\DatabaseShipmentEventReportRepository;
use App\Listeners\EnqueueDomainEventProcessing;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class MonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditLogRepository::class, EloquentAuditLogRepository::class);
        $this->app->bind(SystemJobRepository::class, EloquentSystemJobRepository::class);
        $this->app->bind(DomainEventRepository::class, EloquentDomainEventRepository::class);
        $this->app->bind(DatabaseHealthProbe::class, DatabaseConnectionHealthProbe::class);
        $this->app->bind(DispatchEventReportRepository::class, DatabaseDispatchEventReportRepository::class);
        $this->app->bind(OrderEventReportRepository::class, DatabaseOrderEventReportRepository::class);
        $this->app->bind(NotificationEventReportRepository::class, DatabaseNotificationEventReportRepository::class);
        $this->app->bind(ShipmentEventReportRepository::class, DatabaseShipmentEventReportRepository::class);

        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(SystemJobPolicyService::class, function ($app): SystemJobPolicyService {
            $policySources = [];
            $recurring = config('tracking.jobs.recurring', []);
            if (is_array($recurring)) {
                $policySources[] = $recurring;
            }

            $additionalPolicies = config('tracking.jobs.policies', []);
            if (is_array($additionalPolicies)) {
                $policySources[] = $additionalPolicies;
            }

            $policies = [];
            foreach ($policySources as $source) {
                $policies = array_merge($policies, $source);
            }

            return new SystemJobPolicyService($policies);
        });
        $this->app->singleton(SystemJobTrackingCoordinator::class, function ($app): SystemJobTrackingCoordinator {
            return new SystemJobTrackingCoordinator($app->make(TrackingJobService::class));
        });
        $this->app->singleton(SystemJobRetryService::class, function ($app): SystemJobRetryService {
            return new SystemJobRetryService($app->make(TrackingJobService::class));
        });
        $this->app->singleton(SystemJobAlertService::class, function ($app): SystemJobAlertService {
            return new SystemJobAlertService($app->make(TrackingAlertService::class));
        });
        $this->app->singleton(SystemJobFailureStreakService::class, function ($app): SystemJobFailureStreakService {
            return new SystemJobFailureStreakService($app->make(SystemJobRepository::class));
        });
        $this->app->singleton(SystemJobLifecycleService::class, function ($app): SystemJobLifecycleService {
            return new SystemJobLifecycleService(
                $app->make(SystemJobRepository::class),
                $app->make(SystemJobPolicyService::class),
                $app->make(SystemJobTrackingCoordinator::class),
                $app->make(SystemJobRetryService::class),
                $app->make(SystemJobAlertService::class),
                $app->make(SystemJobFailureStreakService::class),
            );
        });
        $this->app->singleton(DomainEventService::class);
        $this->app->singleton(DomainEventProjector::class);
        $this->app->singleton(ShipmentEventProjector::class);
        $this->app->singleton(DispatchEventProjector::class);
        $this->app->singleton(OrderEventProjector::class);
        $this->app->singleton(NotificationEventProjector::class);
        $this->app->singleton(ListAuditLogs::class);
        $this->app->singleton(ListSystemJobs::class);
        $this->app->singleton(ListDomainEvents::class);
        $this->app->singleton(DomainEventAlertDispatcher::class);
        $this->app->singleton(HealthCheckService::class);

        $this->app->singleton(MetricsRecorder::class, function ($app) {
            $config = $app['config']->get('monitoring.statsd', []);

            if (empty($config['enabled']) === false) {
                return new StatsDMetricsRecorder($config);
            }

            return new NullMetricsRecorder;
        });

        $this->app->alias(MetricsRecorder::class, 'monitoring.metrics');
    }

    public function boot(): void
    {
        $this->registerDomainEventAlerts();

        if (config('monitoring.statsd.enabled')) {
            $this->registerStatsdListeners($this->app->make(MetricsRecorder::class));
        }
    }

    private function registerStatsdListeners(MetricsRecorder $metrics): void
    {
        $jobTimers = [];

        Event::listen(QueryExecuted::class, static function (QueryExecuted $event) use ($metrics): void {
            $metrics->timing('database.query_time', (float) $event->time, [
                'connection' => $event->connectionName !== '' ? $event->connectionName : 'default',
            ]);
        });

        Event::listen(JobProcessing::class, static function (JobProcessing $event) use (&$jobTimers): void {
            $jobTimers[spl_object_id($event->job)] = microtime(true);
        });

        Event::listen(JobProcessed::class, static function (JobProcessed $event) use (&$jobTimers, $metrics): void {
            $key = spl_object_id($event->job);
            $startedAt = $jobTimers[$key] ?? null;
            unset($jobTimers[$key]);

            $tags = [
                'connection' => $event->connectionName !== '' ? $event->connectionName : 'default',
                'queue' => $event->job->getQueue() ?: 'default',
            ];

            $metrics->increment('queue.jobs.processed', 1, $tags);

            if ($startedAt !== null) {
                $durationMs = (microtime(true) - $startedAt) * 1000;
                $metrics->timing('queue.job.duration', (float) $durationMs, $tags);
            }
        });

        Event::listen(JobExceptionOccurred::class, static function (JobExceptionOccurred $event) use (&$jobTimers, $metrics): void {
            unset($jobTimers[spl_object_id($event->job)]);

            $metrics->increment('queue.jobs.failed', 1, [
                'connection' => $event->connectionName !== '' ? $event->connectionName : 'default',
                'queue' => $event->job->getQueue() ?: 'default',
            ]);
        });
    }

    private function registerDomainEventAlerts(): void
    {
        /** @var DomainEventAlertDispatcher $dispatcher */
        $dispatcher = $this->app->make(DomainEventAlertDispatcher::class);

        Event::listen(DomainEventRecorded::class, EnqueueDomainEventProcessing::class);

        Event::listen(DomainEventRecorded::class, static function (DomainEventRecorded $event) use ($dispatcher): void {
            $dispatcher->dispatch($event->record);
        });
    }
}
