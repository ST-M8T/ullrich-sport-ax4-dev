<?php

use App\Http\Controllers\Api\Admin\LogFileController;
use App\Http\Controllers\Api\Admin\SystemSettingController as AdminSystemSettingController;
use App\Http\Controllers\Api\Admin\SystemStatusController;
use App\Http\Controllers\Api\DispatchListController;
use App\Http\Controllers\Api\DispatchScanController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\TrackingAlertController;
use App\Http\Controllers\Api\TrackingJobController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('dispatch-lists', [DispatchListController::class, 'index']);
    Route::get('dispatch-lists/{list}/scans', [DispatchScanController::class, 'index'])
        ->whereNumber('list')
        ->name('api.dispatch-lists.scans');
    Route::post('dispatch-lists/{list}/scans', [DispatchScanController::class, 'store'])
        ->whereNumber('list')
        ->name('api.dispatch-lists.scans.store');
    Route::get('shipments/{trackingNumber}', [ShipmentController::class, 'show']);
    Route::get('tracking-jobs', [TrackingJobController::class, 'index']);
    Route::get('tracking-alerts', [TrackingAlertController::class, 'index']);
    Route::get('settings/{key}', [SystemSettingController::class, 'show'])
        ->middleware('auth.admin')
        ->middleware('can:configuration.settings.manage');

    Route::prefix('health')->group(function () {
        Route::get('live', [HealthCheckController::class, 'live']);
        Route::get('ready', [HealthCheckController::class, 'ready']);
    });
});

// Permission-Gating fuer api/admin/*: spiegelt routes/web.php Pendant.
// Das auth.admin-Middleware (EnsureAdminApiAuthenticated) prueft ausschliesslich
// Authentifizierung. Die Autorisierung pro Endpoint erfolgt zwingend ueber das
// can:<permission>-Middleware analog zu den Web-Routen (Engineering-Handbuch
// Section 19/20: Auth und Authz getrennt, Rechtepruefung niemals nur am Rand).
Route::prefix('admin')
    ->middleware('auth.admin')
    ->group(function () {
        Route::get('system-status', SystemStatusController::class)
            ->middleware('can:admin.setup.view');

        Route::get('system-settings', [AdminSystemSettingController::class, 'index'])
            ->middleware('can:configuration.settings.manage');
        Route::post('system-settings', [AdminSystemSettingController::class, 'store'])
            ->middleware('can:configuration.settings.manage');
        Route::get('system-settings/{settingKey}', [AdminSystemSettingController::class, 'show'])
            ->where('settingKey', '[^/]+')
            ->middleware('can:configuration.settings.manage');
        Route::patch('system-settings/{settingKey}', [AdminSystemSettingController::class, 'update'])
            ->where('settingKey', '[^/]+')
            ->middleware('can:configuration.settings.manage');
        Route::delete('system-settings/{settingKey}', [AdminSystemSettingController::class, 'destroy'])
            ->where('settingKey', '[^/]+')
            ->middleware('can:configuration.settings.manage');

        Route::get('log-files', [LogFileController::class, 'index'])
            ->middleware('can:admin.logs.view');
        Route::get('log-files/{file}/entries', [LogFileController::class, 'entries'])
            ->where('file', '[^/]+')
            ->middleware('can:admin.logs.view');
        Route::post('log-files/{file}/actions/download', [LogFileController::class, 'download'])
            ->where('file', '[^/]+')
            ->middleware('can:admin.logs.view');
        Route::delete('log-files/{file}', [LogFileController::class, 'destroy'])
            ->where('file', '[^/]+')
            ->middleware('can:admin.logs.view');
        Route::get('log-files/{file}', [LogFileController::class, 'show'])
            ->where('file', '[^/]+')
            ->middleware('can:admin.logs.view');
    });
