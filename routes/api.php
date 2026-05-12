<?php

use App\Http\Controllers\Api\Admin\DhlBookingController;
use App\Http\Controllers\Api\Admin\DhlBulkBookingController;
use App\Http\Controllers\Api\Admin\DhlBulkCancellationController;
use App\Http\Controllers\Api\Admin\DhlCancellationController;
use App\Http\Controllers\Api\Admin\DhlLabelController;
use App\Http\Controllers\Api\Admin\DhlPriceQuoteController;
use App\Http\Controllers\Api\Admin\DhlProductCatalogController;
use App\Http\Controllers\Api\Admin\DhlTimetableController;
use App\Http\Controllers\Api\Admin\DhlTrackingEventsController;
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

        Route::get('dhl/products', [DhlProductCatalogController::class, 'listProducts'])
            ->middleware('can:fulfillment.orders.view')
            ->name('api.dhl.products');

        Route::get('dhl/services', [DhlProductCatalogController::class, 'listAdditionalServices'])
            ->middleware('can:fulfillment.orders.view')
            ->name('api.dhl.services');

        Route::get('dhl/tracking/{trackingNumber}/events', [DhlTrackingEventsController::class, 'show'])
            ->where('trackingNumber', '.+')
            ->middleware('can:fulfillment.orders.view')
            ->name('dhl.tracking.events');

        Route::post('dhl/validate-services', [DhlProductCatalogController::class, 'validateServices'])
            ->middleware('can:fulfillment.orders.view')
            ->name('api.dhl.validate-services');

        Route::get('dhl/timetable', [DhlTimetableController::class, 'show'])
            ->middleware('can:fulfillment.orders.view');

        Route::post('dhl/booking', [DhlBookingController::class, 'store'])
            ->middleware('can:fulfillment.orders.manage');

        Route::post('dhl/bulk-book', [DhlBulkBookingController::class, 'store'])
            ->middleware('can:fulfillment.orders.manage');

        Route::get('dhl/booking/{shipmentOrderId}', [DhlBookingController::class, 'show'])
            ->whereNumber('shipmentOrderId')
            ->middleware('can:fulfillment.orders.view');

        Route::get('dhl/price-quote', [DhlPriceQuoteController::class, 'show'])
            ->middleware('can:fulfillment.orders.view');

        Route::get('dhl/label/{shipmentOrderId}', [DhlLabelController::class, 'show'])
            ->whereNumber('shipmentOrderId')
            ->middleware('can:fulfillment.orders.view');

        Route::delete('dhl/shipment/{shipmentOrderId}', [DhlCancellationController::class, 'destroy'])
            ->whereNumber('shipmentOrderId')
            ->middleware('can:fulfillment.orders.manage')
            ->name('dhl.shipment.cancel');

        Route::post('dhl/bulk-cancel', [DhlBulkCancellationController::class, 'store'])
            ->middleware('can:fulfillment.orders.manage')
            ->name('dhl.bulk-cancel');

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
