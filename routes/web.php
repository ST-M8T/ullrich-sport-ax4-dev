<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Configuration\MailTemplateController;
use App\Http\Controllers\Configuration\NotificationController;
use App\Http\Controllers\Configuration\SystemSettingController;
use App\Http\Controllers\Dispatch\DispatchListController;
use App\Http\Controllers\Fulfillment\CsvExportController;
use App\Http\Controllers\Fulfillment\FulfillmentMasterdataController;
use App\Http\Controllers\Fulfillment\Masterdata\AssemblyOptionController;
use App\Http\Controllers\Fulfillment\Masterdata\FreightProfileController;
use App\Http\Controllers\Fulfillment\Masterdata\PackagingProfileController;
use App\Http\Controllers\Fulfillment\Masterdata\SenderProfileController;
use App\Http\Controllers\Fulfillment\Masterdata\SenderRuleController;
use App\Http\Controllers\Fulfillment\Masterdata\VariationProfileController;
use App\Http\Controllers\Fulfillment\ShipmentAdminController;
use App\Http\Controllers\Fulfillment\ShipmentOrderActionController;
use App\Http\Controllers\Fulfillment\ShipmentOrderController;
use App\Http\Controllers\Identity\UserDetailController;
use App\Http\Controllers\Identity\UserIndexController;
use App\Http\Controllers\Identity\UserManagementController;
use App\Http\Controllers\Monitoring\AuditLogController;
use App\Http\Controllers\Monitoring\DomainEventController;
use App\Http\Controllers\Monitoring\LogController;
use App\Http\Controllers\Monitoring\SetupController;
use App\Http\Controllers\Monitoring\SystemJobController;
use App\Http\Controllers\Tracking\TrackingAlertController;
use App\Http\Controllers\Tracking\TrackingJobController;
use App\Http\Controllers\Tracking\TrackingOverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])
    ->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->name('login.perform');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dispatch-lists');
    }

    if (Route::has('login')) {
        return redirect()->route('login');
    }

    return redirect()->route('dispatch-lists');
});

Route::middleware(['web', 'auth', 'can:admin.access'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/fulfillment/masterdata', [FulfillmentMasterdataController::class, 'index'])
            ->middleware('can:fulfillment.masterdata.manage')
            ->name('fulfillment-masterdata');

        Route::prefix('/fulfillment/masterdata')
            ->name('fulfillment.masterdata.')
            ->middleware('can:fulfillment.masterdata.manage')
            ->group(function () {
                Route::resource('packaging-profiles', PackagingProfileController::class)
                    ->parameters(['packaging-profiles' => 'packagingProfile'])
                    ->names('packaging')
                    ->middleware('can:fulfillment.masterdata.manage')
                    ->except(['show']);

                Route::resource('assembly-options', AssemblyOptionController::class)
                    ->parameters(['assembly-options' => 'assemblyOption'])
                    ->names('assembly')
                    ->middleware('can:fulfillment.masterdata.manage')
                    ->except(['show']);

                Route::resource('variation-profiles', VariationProfileController::class)
                    ->parameters(['variation-profiles' => 'variationProfile'])
                    ->names('variations')
                    ->middleware('can:fulfillment.masterdata.manage')
                    ->except(['show']);

                Route::resource('sender-profiles', SenderProfileController::class)
                    ->parameters(['sender-profiles' => 'senderProfile'])
                    ->names('senders')
                    ->middleware('can:fulfillment.masterdata.manage')
                    ->except(['show']);

                Route::resource('sender-rules', SenderRuleController::class)
                    ->parameters(['sender-rules' => 'senderRule'])
                    ->names('sender-rules')
                    ->middleware('can:fulfillment.masterdata.manage')
                    ->except(['show']);

                Route::resource('freight-profiles', FreightProfileController::class)
                    ->parameters(['freight-profiles' => 'freightProfile'])
                    ->names('freight')
                    ->middleware('can:fulfillment.masterdata.manage')
                    ->except(['show']);
            });

        Route::get('/fulfillment/orders', [ShipmentOrderController::class, 'index'])
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders');

        Route::get('/csv-export', [CsvExportController::class, 'index'])
            ->middleware('can:fulfillment.csv_export.manage')
            ->name('csv-export');
        Route::post('/csv-export', [CsvExportController::class, 'trigger'])
            ->middleware('can:fulfillment.csv_export.manage')
            ->name('csv-export.trigger');
        Route::post('/csv-export/{job}/retry', [CsvExportController::class, 'retry'])
            ->whereNumber('job')
            ->middleware('can:fulfillment.csv_export.manage')
            ->name('csv-export.retry');
        Route::get('/csv-export/download', [CsvExportController::class, 'download'])
            ->middleware('can:fulfillment.csv_export.manage')
            ->name('csv-export.download');
        Route::get('/fulfillment/orders/{order}', [ShipmentOrderController::class, 'show'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.show');
        Route::post('/fulfillment/orders/{order}/book', [ShipmentOrderController::class, 'book'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.book');
        Route::post('/fulfillment/orders/{order}/tracking-transfer', [ShipmentOrderController::class, 'transfer'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.transfer');

        Route::post('/fulfillment/orders/{order}/sender-profile', [ShipmentOrderController::class, 'assignSenderProfile'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.sender-profile');

        Route::post('/fulfillment/orders/{order}/dhl/book', [ShipmentOrderController::class, 'bookDhl'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.dhl.book');

        Route::get('/fulfillment/orders/{order}/dhl/label', [ShipmentOrderController::class, 'previewLabel'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.dhl.label');

        Route::get('/fulfillment/orders/{order}/dhl/label/download', [ShipmentOrderController::class, 'downloadLabel'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.dhl.label.download');

        Route::get('/fulfillment/orders/{order}/dhl/label/preview', [ShipmentOrderController::class, 'previewLabel'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.dhl.label.preview');

        Route::get('/fulfillment/orders/{order}/dhl/price-quote', [ShipmentOrderController::class, 'getPriceQuote'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.dhl.price-quote');

        Route::post('/fulfillment/orders/{order}/dhl/cancel', [ShipmentOrderController::class, 'cancelDhl'])
            ->whereNumber('order')
            ->middleware('can:fulfillment.orders.manage')
            ->name('fulfillment-orders.dhl.cancel');

        Route::post('/fulfillment/orders/actions/sync-visible', [ShipmentOrderActionController::class, 'syncVisible'])
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.sync-visible');

        Route::post('/fulfillment/orders/actions/sync-booked', [ShipmentOrderActionController::class, 'syncBooked'])
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.sync-booked');

        Route::post('/fulfillment/orders/actions/manual-sync', [ShipmentOrderActionController::class, 'manualSync'])
            ->middleware('can:fulfillment.orders.view')
            ->name('fulfillment-orders.sync-manual');

        Route::get('/fulfillment/shipments', [ShipmentAdminController::class, 'index'])
            ->middleware('can:fulfillment.shipments.manage')
            ->name('fulfillment-shipments');

        Route::post('/fulfillment/shipments/{shipment}/sync', [ShipmentAdminController::class, 'sync'])
            ->whereNumber('shipment')
            ->middleware('can:fulfillment.shipments.manage')
            ->name('fulfillment-shipments.sync');

        Route::get('/dispatch/lists', [DispatchListController::class, 'index'])
            ->middleware('can:dispatch.lists.manage')
            ->name('dispatch-lists');

        Route::get('/setup', [SetupController::class, 'index'])
            ->middleware('can:admin.setup.view')
            ->name('monitoring-health');

        Route::get('/logs', [LogController::class, 'index'])
            ->middleware('can:admin.logs.view')
            ->name('monitoring-logs');

        Route::get('/logs/download', [LogController::class, 'download'])
            ->middleware('can:admin.logs.view')
            ->name('monitoring-logs.download');

        // 301 redirects for deprecated route names (SEO/Bookmarks)
        Route::redirect('/admin/admin-setup', '/admin/setup', 301)->name('admin-setup');
        Route::redirect('/admin/admin-logs', '/admin/logs', 301)->name('admin-logs');
        Route::redirect('/admin/admin-logs/download', '/admin/logs/download', 301)->name('admin-logs.download');
        Route::post('/dispatch/lists/{list}/close', [DispatchListController::class, 'close'])
            ->whereNumber('list')
            ->middleware('can:dispatch.lists.manage')
            ->name('dispatch-lists.close');
        Route::get('/dispatch/lists/{list}/scans', [DispatchListController::class, 'scans'])
            ->whereNumber('list')
            ->middleware('can:dispatch.lists.manage')
            ->name('dispatch-lists.scans');
        Route::post('/dispatch/lists/{list}/export', [DispatchListController::class, 'export'])
            ->whereNumber('list')
            ->middleware('can:dispatch.lists.manage')
            ->name('dispatch-lists.export');

        Route::prefix('tracking')->group(function () {
            Route::get('/', [TrackingOverviewController::class, 'index'])
                ->middleware('can:tracking.overview.view')
                ->name('tracking-overview');

            Route::get('/jobs/{job}', [TrackingJobController::class, 'show'])
                ->whereNumber('job')
                ->middleware('can:tracking.jobs.manage')
                ->name('tracking-jobs.show');

            Route::post('/jobs/{job}/retry', [TrackingJobController::class, 'retry'])
                ->whereNumber('job')
                ->middleware('can:tracking.jobs.manage')
                ->name('tracking-jobs.retry');

            Route::post('/jobs/{job}/fail', [TrackingJobController::class, 'markFailed'])
                ->whereNumber('job')
                ->middleware('can:tracking.jobs.manage')
                ->name('tracking-jobs.fail');

            Route::get('/alerts/{alert}', [TrackingAlertController::class, 'show'])
                ->whereNumber('alert')
                ->middleware('can:tracking.overview.view')
                ->name('tracking-alerts.show');

            Route::post('/alerts/{alert}/acknowledge', [TrackingAlertController::class, 'acknowledge'])
                ->whereNumber('alert')
                ->middleware('can:tracking.alerts.manage')
                ->name('tracking-alerts.acknowledge');
        });

        Route::get('/identity/users', [UserIndexController::class, 'index'])
            ->middleware('can:identity.users.manage')
            ->name('identity-users');

        Route::get('/identity/users/{user}', [UserDetailController::class, 'show'])
            ->whereNumber('user')
            ->middleware('can:identity.users.manage')
            ->name('identity-users.show');

        Route::get('/identity/users/create', [UserManagementController::class, 'create'])
            ->middleware('can:identity.users.manage')
            ->name('identity-users.create');

        Route::post('/identity/users', [UserManagementController::class, 'store'])
            ->middleware('can:identity.users.manage')
            ->name('identity-users.store');

        Route::get('/identity/users/{user}/edit', [UserManagementController::class, 'edit'])
            ->whereNumber('user')
            ->middleware('can:identity.users.manage')
            ->name('identity-users.edit');

        Route::put('/identity/users/{user}', [UserManagementController::class, 'update'])
            ->whereNumber('user')
            ->middleware('can:identity.users.manage')
            ->name('identity-users.update');

        Route::post('/identity/users/{user}/password', [UserManagementController::class, 'resetPassword'])
            ->whereNumber('user')
            ->middleware('can:identity.users.manage')
            ->name('identity-users.reset-password');

        Route::post('/identity/users/{user}/status', [UserManagementController::class, 'updateStatus'])
            ->whereNumber('user')
            ->middleware('can:identity.users.manage')
            ->name('identity-users.update-status');

        Route::get('/configuration/settings', [SystemSettingController::class, 'index'])
            ->middleware('can:configuration.settings.manage')
            ->name('configuration-settings');

        Route::get('/configuration/settings/create', [SystemSettingController::class, 'create'])
            ->middleware('can:configuration.settings.manage')
            ->name('configuration-settings.create');

        Route::post('/configuration/settings', [SystemSettingController::class, 'store'])
            ->middleware('can:configuration.settings.manage')
            ->name('configuration-settings.store');

        Route::get('/configuration/settings/{settingKey}/edit', [SystemSettingController::class, 'edit'])
            ->where('settingKey', '[^/]+')
            ->middleware('can:configuration.settings.manage')
            ->name('configuration-settings.edit');

        Route::put('/configuration/settings/{settingKey}', [SystemSettingController::class, 'update'])
            ->where('settingKey', '[^/]+')
            ->middleware('can:configuration.settings.manage')
            ->name('configuration-settings.update');

        Route::post('/configuration/settings/groups/{group}', [SystemSettingController::class, 'updateGroup'])
            ->where('group', '[a-z0-9_\-]+')
            ->middleware('can:configuration.settings.manage')
            ->name('configuration-settings.group-update');

        Route::get('/configuration/mail-templates', [MailTemplateController::class, 'index'])
            ->middleware('can:configuration.mail_templates.manage')
            ->name('configuration-mail-templates');

        Route::get('/configuration/mail-templates/create', [MailTemplateController::class, 'create'])
            ->middleware('can:configuration.mail_templates.manage')
            ->name('configuration-mail-templates.create');

        Route::post('/configuration/mail-templates', [MailTemplateController::class, 'store'])
            ->middleware('can:configuration.mail_templates.manage')
            ->name('configuration-mail-templates.store');

        Route::get('/configuration/mail-templates/{templateKey}/edit', [MailTemplateController::class, 'edit'])
            ->where('templateKey', '[^/]+')
            ->middleware('can:configuration.mail_templates.manage')
            ->name('configuration-mail-templates.edit');

        Route::put('/configuration/mail-templates/{templateKey}', [MailTemplateController::class, 'update'])
            ->where('templateKey', '[^/]+')
            ->middleware('can:configuration.mail_templates.manage')
            ->name('configuration-mail-templates.update');

        Route::delete('/configuration/mail-templates/{templateKey}', [MailTemplateController::class, 'destroy'])
            ->where('templateKey', '[^/]+')
            ->middleware('can:configuration.mail_templates.manage')
            ->name('configuration-mail-templates.destroy');

        Route::get('/configuration/mail-templates/{templateKey}/preview', [MailTemplateController::class, 'preview'])
            ->where('templateKey', '[^/]+')
            ->middleware('can:configuration.mail_templates.manage')
            ->name('configuration-mail-templates.preview');

        Route::get('/configuration/notifications', [NotificationController::class, 'index'])
            ->middleware('can:configuration.notifications.manage')
            ->name('configuration-notifications');

        Route::post('/configuration/notifications', [NotificationController::class, 'store'])
            ->middleware('can:configuration.notifications.manage')
            ->name('configuration-notifications.store');

        Route::post('/configuration/notifications/settings', [NotificationController::class, 'updateSettings'])
            ->middleware('can:configuration.notifications.manage')
            ->name('configuration-notifications.settings');

        Route::post('/configuration/notifications/dispatch', [NotificationController::class, 'dispatch'])
            ->middleware('can:configuration.notifications.manage')
            ->name('configuration-notifications.dispatch');

        Route::post('/configuration/notifications/{notification}/redispatch', [NotificationController::class, 'redispatch'])
            ->whereNumber('notification')
            ->middleware('can:configuration.notifications.manage')
            ->name('configuration-notifications.redispatch');

        Route::get('/configuration/integrations', [\App\Http\Controllers\Configuration\IntegrationController::class, 'index'])
            ->middleware('can:configuration.integrations.manage')
            ->name('configuration-integrations');

        Route::get('/configuration/integrations/{integrationKey}', [\App\Http\Controllers\Configuration\IntegrationController::class, 'show'])
            ->where('integrationKey', '[a-z0-9_\-]+')
            ->middleware('can:configuration.integrations.manage')
            ->name('configuration-integrations.show');

        Route::put('/configuration/integrations/{integrationKey}', [\App\Http\Controllers\Configuration\IntegrationController::class, 'update'])
            ->where('integrationKey', '[a-z0-9_\-]+')
            ->middleware('can:configuration.integrations.manage')
            ->name('configuration-integrations.update');

        Route::post('/configuration/integrations/{integrationKey}/test', [\App\Http\Controllers\Configuration\IntegrationController::class, 'test'])
            ->where('integrationKey', '[a-z0-9_\-]+')
            ->middleware('can:configuration.integrations.manage')
            ->name('configuration-integrations.test');

        Route::get('/monitoring/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('can:monitoring.audit_logs.view')
            ->name('monitoring-audit-logs');

        Route::get('/monitoring/system-jobs', [SystemJobController::class, 'index'])
            ->middleware('can:monitoring.system_jobs.view')
            ->name('monitoring-system-jobs');

        Route::get('/monitoring/domain-events', [DomainEventController::class, 'index'])
            ->middleware('can:monitoring.domain_events.view')
            ->name('monitoring-domain-events');
    });
