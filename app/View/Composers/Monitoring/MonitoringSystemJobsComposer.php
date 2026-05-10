<?php

declare(strict_types=1);

namespace App\View\Composers\Monitoring;

use Illuminate\View\View;

/**
 * Monitoring System Jobs View Composer
 * Bereitet System-Jobs-Index-Daten vor
 */
final class MonitoringSystemJobsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $statusOptions = $data['statusOptions'] ?? [];
        $timeRanges = $data['timeRanges'] ?? [];
        $filters = $data['filters'] ?? [];

        $view->with([
            'statusOptions' => $statusOptions,
            'timeRanges' => $timeRanges,
            'filters' => $filters,
        ]);
    }
}
