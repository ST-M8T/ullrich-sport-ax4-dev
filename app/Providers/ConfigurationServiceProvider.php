<?php

namespace App\Providers;

use App\Application\Configuration\Channels\LoggingSmsSender;
use App\Application\Configuration\Channels\MailNotificationChannel;
use App\Application\Configuration\Channels\SlackNotificationChannel;
use App\Application\Configuration\Channels\SmsNotificationChannel;
use App\Application\Configuration\Channels\SmsSender;
use App\Application\Configuration\Events\NotificationSent;
use App\Application\Configuration\Listeners\NotificationSentRecorder;
use App\Application\Configuration\MailTemplateService;
use App\Application\Configuration\NotificationDispatchService;
use App\Application\Configuration\NotificationService;
use App\Application\Configuration\Queries\ListMailTemplates;
use App\Application\Configuration\Queries\ListNotifications;
use App\Application\Configuration\Queries\ListSystemSettings;
use App\Application\Configuration\SecretEncryptionService;
use App\Application\Configuration\SecretRotationService;
use App\Application\Configuration\SystemSettingService;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Configuration\Contracts\MailTemplateRepository;
use App\Domain\Configuration\Contracts\NotificationRepository;
use App\Domain\Configuration\Contracts\SecretRotationRepository;
use App\Domain\Configuration\Contracts\SystemSettingRepository;
use App\Infrastructure\Persistence\Configuration\Eloquent\EloquentMailTemplateRepository;
use App\Infrastructure\Persistence\Configuration\Eloquent\EloquentNotificationRepository;
use App\Infrastructure\Persistence\Configuration\Eloquent\EloquentSecretRotationRepository;
use App\Infrastructure\Persistence\Configuration\Eloquent\EloquentSystemSettingRepository;
use App\Support\Configuration\SystemSettingConfigMapper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ConfigurationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SystemSettingRepository::class, EloquentSystemSettingRepository::class);
        $this->app->bind(SecretRotationRepository::class, EloquentSecretRotationRepository::class);
        $this->app->bind(MailTemplateRepository::class, EloquentMailTemplateRepository::class);
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);

        $this->app->singleton(SecretRotationService::class);
        $this->app->singleton(SecretEncryptionService::class);
        $this->app->singleton(SystemSettingService::class, function ($app): SystemSettingService {
            return new SystemSettingService(
                $app->make(SystemSettingRepository::class),
                $app->make(SecretRotationService::class),
                $app->make(AuditLogger::class),
                $app->make(SecretEncryptionService::class),
            );
        });
        $this->app->singleton(SystemSettingConfigMapper::class, function ($app) {
            return new SystemSettingConfigMapper($app->make(SystemSettingService::class));
        });
        $this->app->singleton(MailTemplateService::class);
        $this->app->singleton(NotificationService::class);

        $this->app->singleton(SmsSender::class, LoggingSmsSender::class);
        $this->app->singleton(MailNotificationChannel::class);
        $this->app->singleton(SlackNotificationChannel::class);
        $this->app->singleton(SmsNotificationChannel::class);

        $this->app->singleton(NotificationDispatchService::class, function ($app) {
            $channels = [
                $app->make(MailNotificationChannel::class),
                $app->make(SlackNotificationChannel::class),
                $app->make(SmsNotificationChannel::class),
            ];

            return new NotificationDispatchService(
                $app->make(NotificationRepository::class),
                $channels,
            );
        });
        $this->app->singleton(ListSystemSettings::class);
        $this->app->singleton(ListMailTemplates::class);
        $this->app->singleton(ListNotifications::class);
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            $this->app->make(SystemSettingConfigMapper::class)->apply();
        });

        Event::listen(NotificationSent::class, NotificationSentRecorder::class);
    }
}
