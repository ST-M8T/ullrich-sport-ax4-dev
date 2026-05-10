<?php

use App\Console\Commands\Configuration\DispatchNotificationsCommand;
use App\Console\Commands\Dispatch\CaptureDispatchScanCommand;
use App\Console\Commands\Dispatch\CloseDispatchListCommand;
use App\Console\Commands\Fulfillment\SyncOrdersCommand;
use App\Console\Commands\Identity\CreateUserCommand;
use App\Console\Commands\Integrations\DhlPingCommand;
use App\Console\Commands\Integrations\DhlSyncTrackingCommand;
use App\Console\Commands\Integrations\PlentyPingCommand;
use App\Console\Commands\Integrations\PlentySyncOrdersCommand;
use App\Console\Commands\MigrateFulfillmentMasterdata;
use App\Console\Commands\MigrateFulfillmentOperations;
use App\Console\Commands\Monitoring\ExportLogsCommand;
use App\Console\Commands\Performance\BenchmarkDomainPerformanceCommand;
use App\Console\Commands\Tracking\CompleteTrackingJobCommand;
use App\Console\Commands\Tracking\RaiseTrackingAlertCommand;
use App\Console\Commands\Tracking\RetryTrackingJobCommand;
use App\Console\Commands\Tracking\RunDueTrackingJobsCommand;
use App\Console\Commands\Tracking\ScheduleTrackingJobCommand;
use App\Console\Commands\WarmDomainCachesCommand;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnforceSecurityHeaders;
use App\Http\Middleware\EnsureAdminApiAuthenticated;
use App\Http\Middleware\RecordRequestMetrics;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        MigrateFulfillmentMasterdata::class,
        MigrateFulfillmentOperations::class,
        ScheduleTrackingJobCommand::class,
        CompleteTrackingJobCommand::class,
        RaiseTrackingAlertCommand::class,
        CaptureDispatchScanCommand::class,
        CloseDispatchListCommand::class,
        CreateUserCommand::class,
        RunDueTrackingJobsCommand::class,
        RetryTrackingJobCommand::class,
        PlentySyncOrdersCommand::class,
        SyncOrdersCommand::class,
        DhlSyncTrackingCommand::class,
        PlentyPingCommand::class,
        DhlPingCommand::class,
        DispatchNotificationsCommand::class,
        ExportLogsCommand::class,
        WarmDomainCachesCommand::class,
        BenchmarkDomainPerformanceCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.api' => AuthenticateApiKey::class,
            'auth.admin' => EnsureAdminApiAuthenticated::class,
            'throttle' => ThrottleRequests::class,
        ]);

        $middleware->prependToGroup('api', 'throttle:secure-api');
        $middleware->appendToGroup('api', AuthenticateApiKey::class);
        $middleware->appendToGroup('api', RecordRequestMetrics::class);
        $middleware->appendToGroup('api', EnforceSecurityHeaders::class);

        $middleware->appendToGroup('web', RecordRequestMetrics::class);
        $middleware->appendToGroup('web', EnforceSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
            }

            return null;
        });

        $exceptions->report(function (Throwable $throwable) {
            if (app()->bound('sentry') && config('sentry.dsn')) {
                app('sentry')->captureException($throwable);
            }
        });
    })->create();
