<?php

namespace App\Providers;

use App\Application\Identity\Authorization\RoleManager;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('local', 'testing', 'dusk')) {
            $this->app->register(DuskServiceProvider::class);
        }
    }

    public function boot(): void
    {
        /** @var RoleManager $roles */
        $roles = $this->app->make(RoleManager::class);
        UserModel::setRoleManager($roles);

        $this->configureAdminTokenGuard();
        $this->configureRateLimiting();
        $this->configureViewComposers();
    }

    private function configureAdminTokenGuard(): void
    {
        Auth::viaRequest('admin-token', function (Request $request): ?GenericUser {
            $expected = (string) config('services.admin_api.token', '');
            if ($expected === '') {
                return null;
            }

            $provided = $request->bearerToken()
                ?? $request->header('X-Admin-Token')
                ?? $request->header('X-API-Key')
                ?? $request->query('admin_token');

            if ($provided === null) {
                return null;
            }

            if (! hash_equals($expected, (string) $provided)) {
                return null;
            }

            return new GenericUser([
                'id' => 'admin-token',
                'name' => 'Admin API Token',
            ]);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('secure-api', function (Request $request): Limit {
            $maxAttempts = max(1, (int) config('security.rate_limiting.api.max_attempts', 120));
            $decaySeconds = max(1, (int) config('security.rate_limiting.api.decay_seconds', 60));
            $decayMinutes = max(1, (int) ceil($decaySeconds / 60));

            $apiKey = $request->header('X-API-Key')
                ?? $request->query('api_key')
                ?? $request->bearerToken();

            $key = implode('|', array_filter([
                $apiKey && is_scalar($apiKey) ? 'api:'.(string) $apiKey : null,
                $request->ip() ? 'ip:'.$request->ip() : null,
            ]));

            if ($key === '') {
                $key = 'ip:'.($request->ip() ?: 'unknown');
            }

            return Limit::perMinutes($decayMinutes, $maxAttempts)->by($key);
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $maxAttempts = max(1, (int) config('security.rate_limiting.login.max_attempts', 5));
            $decaySeconds = max(1, (int) config('security.rate_limiting.login.decay_seconds', 600));
            $decayMinutes = max(1, (int) ceil($decaySeconds / 60));

            $username = (string) $request->input('username', (string) $request->input('email', ''));
            $identifier = Str::lower(trim($username));
            $ip = $request->ip() ?: 'unknown';

            $key = $identifier !== '' ? sprintf('login:%s|%s', $identifier, $ip) : sprintf('login:ip:%s', $ip);

            return Limit::perMinutes($decayMinutes, $maxAttempts)->by($key);
        });
    }

    private function configureViewComposers(): void
    {
        View::composer('*', function ($view): void {
            $data = $view->getData();
            $authUser = Auth::user();

            if (! $authUser instanceof UserModel) {
                return;
            }

            if (! array_key_exists('identityUser', $data)) {
                $view->with('identityUser', $authUser->toIdentityUser());
            }

            if (! array_key_exists('currentUser', $data)) {
                $view->with('currentUser', $authUser->display_name ?? $authUser->username);
            }

            if (! array_key_exists('userRole', $data)) {
                $view->with('userRole', $authUser->role);
            }

            if (! array_key_exists('isAdmin', $data)) {
                $view->with('isAdmin', $authUser->hasPermission('admin.access'));
            }
        });

        View::composer('components.navigation', \App\View\Composers\Shared\NavigationComposer::class);
        View::composer('configuration.settings.index', \App\View\Composers\Configuration\ConfigurationSettingsComposer::class);
        View::composer('fulfillment.orders.index', \App\View\Composers\Fulfillment\FulfillmentOrdersComposer::class);
        View::composer('fulfillment.masterdata.variations.index', \App\View\Composers\Fulfillment\FulfillmentVariationsComposer::class);
        View::composer('fulfillment.masterdata.assembly.index', \App\View\Composers\Fulfillment\FulfillmentAssemblyComposer::class);
        View::composer('fulfillment.masterdata.sender-rules.index', \App\View\Composers\Fulfillment\FulfillmentSenderRulesComposer::class);
        View::composer('fulfillment.orders.show', \App\View\Composers\Fulfillment\FulfillmentOrderShowComposer::class);
        View::composer('fulfillment.shipments.index', \App\View\Composers\Fulfillment\FulfillmentShipmentsComposer::class);
        View::composer('monitoring.system-jobs.index', \App\View\Composers\Monitoring\MonitoringSystemJobsComposer::class);
        View::composer('monitoring.domain-events.index', \App\View\Composers\Monitoring\MonitoringDomainEventsComposer::class);
        View::composer('fulfillment.masterdata.*', \App\View\Composers\Fulfillment\FulfillmentMasterdataCatalogComposer::class);
        View::composer('monitoring.audit-logs.index', \App\View\Composers\Monitoring\MonitoringAuditLogsComposer::class);
        View::composer('tracking.overview', \App\View\Composers\Tracking\TrackingOverviewComposer::class);
        View::composer('dispatch.lists.index', \App\View\Composers\Dispatch\DispatchListsComposer::class);
        View::composer('dispatch.lists.index', \App\View\Composers\Dispatch\DispatchListsModalComposer::class);
        View::composer('fulfillment.exports.index', \App\View\Composers\Fulfillment\FulfillmentExportsComposer::class);
        View::composer('configuration.integrations.index', \App\View\Composers\Configuration\ConfigurationIntegrationsComposer::class);
        View::composer('configuration.settings.partials.monitoring', \App\View\Composers\Configuration\ConfigurationSettingsMonitoringComposer::class);
        View::composer('configuration.settings.partials.verwaltung', \App\View\Composers\Configuration\ConfigurationSettingsVerwaltungComposer::class);
        View::composer('configuration.settings.partials.logs', \App\View\Composers\Configuration\ConfigurationSettingsLogsComposer::class);
        View::composer('identity.users.index', \App\View\Composers\Identity\IdentityUsersComposer::class);
        View::composer('identity.users.edit', \App\View\Composers\Identity\IdentityUserEditComposer::class);
        View::composer('configuration.settings.partials.settings', \App\View\Composers\Configuration\ConfigurationSettingsSettingsComposer::class);
        View::composer('configuration.settings.partials.tab-nav', \App\View\Composers\Configuration\ConfigurationSettingsTabNavComposer::class);
        View::composer('configuration.settings.partials.monitoring.sections.system-status', \App\View\Composers\Configuration\ConfigurationSettingsSystemStatusComposer::class);
        View::composer('configuration.settings.partials.monitoring.sections.system-jobs', \App\View\Composers\Configuration\ConfigurationSettingsSystemJobsComposer::class);
        View::composer('configuration.settings.partials.logs.sections.system-logs', \App\View\Composers\Configuration\ConfigurationSettingsSystemLogsComposer::class);
        View::composer('components.sidebar-tabs', \App\View\Composers\Shared\SidebarTabsComposer::class);
        View::composer('fulfillment.masterdata.partials.catalog', \App\View\Composers\Fulfillment\FulfillmentMasterdataCatalogPartialComposer::class);
        View::composer('components.filters.filter-tabs', \App\View\Composers\Shared\FilterTabsComposer::class);
        View::composer('configuration.settings._form', \App\View\Composers\Configuration\ConfigurationSettingsFormComposer::class);
        View::composer('components.filters.filter-form', \App\View\Composers\Shared\FilterFormComposer::class);
        View::composer('configuration.integrations.show', \App\View\Composers\Configuration\ConfigurationIntegrationsShowComposer::class);
        View::composer('layouts.admin', \App\View\Composers\Shared\AdminLayoutComposer::class);
        View::composer('components.tabs', \App\View\Composers\Shared\TabsComposer::class);
        View::composer('fulfillment.masterdata.sections.variations', \App\View\Composers\Fulfillment\FulfillmentMasterdataVariationsSectionComposer::class);
        View::composer('fulfillment.masterdata.sections.packaging', \App\View\Composers\Fulfillment\FulfillmentMasterdataPackagingSectionComposer::class);
        View::composer('fulfillment.masterdata.sections.senders', \App\View\Composers\Fulfillment\FulfillmentMasterdataSendersSectionComposer::class);
        View::composer('fulfillment.masterdata.sections.assembly', \App\View\Composers\Fulfillment\FulfillmentMasterdataAssemblySectionComposer::class);
        View::composer('fulfillment.masterdata.sections.sender-rules', \App\View\Composers\Fulfillment\FulfillmentMasterdataSenderRulesSectionComposer::class);
        View::composer('fulfillment.masterdata.sections.freight', \App\View\Composers\Fulfillment\FulfillmentMasterdataFreightSectionComposer::class);
    }
}
