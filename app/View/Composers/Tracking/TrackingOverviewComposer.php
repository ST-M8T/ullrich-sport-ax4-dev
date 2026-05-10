<?php

declare(strict_types=1);

namespace App\View\Composers\Tracking;

use Illuminate\View\View;

/**
 * Tracking Overview View Composer
 * Bereitet Tracking-Overview-Daten vor
 */
final class TrackingOverviewComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $jobFilters = $data['jobFilters'] ?? [];
        $alertFilters = $data['alertFilters'] ?? [];

        $jobStatusOptions = [
            '' => 'Alle Stati',
            'scheduled' => 'Scheduled',
            'running' => 'Running',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ];

        $alertSeverityOptions = [
            '' => 'Alle Severity',
            'info' => 'Info',
            'warning' => 'Warning',
            'error' => 'Error',
            'critical' => 'Critical',
        ];

        $ackOptions = [
            '' => 'Alle',
            '1' => 'Bestätigt',
            '0' => 'Offen',
        ];

        $initialTab = request()->query('tab') === 'alerts' ? 'alerts' : 'jobs';

        $view->with([
            'jobStatusOptions' => $jobStatusOptions,
            'alertSeverityOptions' => $alertSeverityOptions,
            'ackOptions' => $ackOptions,
            'initialTab' => $initialTab,
            'jobFilters' => $jobFilters,
            'alertFilters' => $alertFilters,
        ]);
    }
}
