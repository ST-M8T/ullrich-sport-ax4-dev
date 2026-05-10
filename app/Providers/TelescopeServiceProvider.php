<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! config('telescope.enabled')) {
            return;
        }

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });

        $guard = config('monitoring.telescope.guard');

        Telescope::auth(function ($request) use ($guard) {
            $user = $guard
                ? $this->app->make('auth')->guard($guard)->user()
                : $request->user();

            return $user !== null && Gate::forUser($user)->check('viewTelescope');
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            if (! config('telescope.enabled')) {
                return false;
            }

            $allowed = config('monitoring.telescope.allowed_emails', []);

            if (in_array('*', $allowed, true)) {
                return true;
            }

            if ($user === null || empty($allowed)) {
                return false;
            }

            $email = is_object($user) && method_exists($user, 'getAttribute')
                ? $user->getAttribute('email')
                : (is_object($user) ? ($user->email ?? null) : null);

            if ($email === null) {
                return false;
            }

            return in_array(strtolower($email), array_map('strtolower', $allowed), true);
        });
    }
}
