<?php

namespace App\Providers;

use App\Application\Tracking\Queries\ListTrackingAlerts;
use App\Application\Tracking\Queries\ListTrackingJobs;
use App\Application\Tracking\TrackingAlertService;
use App\Application\Tracking\TrackingJobScheduler;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Tracking\Contracts\TrackingAlertRepository;
use App\Domain\Tracking\Contracts\TrackingJobRepository;
use App\Infrastructure\Persistence\Tracking\Eloquent\EloquentTrackingAlertRepository;
use App\Infrastructure\Persistence\Tracking\Eloquent\EloquentTrackingJobRepository;
use Illuminate\Support\ServiceProvider;

final class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TrackingJobRepository::class, EloquentTrackingJobRepository::class);
        $this->app->bind(TrackingAlertRepository::class, EloquentTrackingAlertRepository::class);

        $this->app->singleton(ListTrackingJobs::class);
        $this->app->singleton(ListTrackingAlerts::class);
        $this->app->singleton(TrackingJobService::class);
        $this->app->singleton(TrackingAlertService::class);
        $this->app->singleton(TrackingJobScheduler::class, function ($app): TrackingJobScheduler {
            $definitions = config('tracking.jobs.recurring', []);

            return new TrackingJobScheduler(
                $app->make(TrackingJobRepository::class),
                $app->make(TrackingJobService::class),
                is_array($definitions) ? $definitions : []
            );
        });
    }
}
