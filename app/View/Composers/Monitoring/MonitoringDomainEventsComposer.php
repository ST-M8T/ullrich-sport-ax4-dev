<?php

declare(strict_types=1);

namespace App\View\Composers\Monitoring;

use Illuminate\View\View;

/**
 * Monitoring Domain Events View Composer
 * Bereitet Domain-Events-Index-Daten vor
 */
final class MonitoringDomainEventsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $timeRanges = $data['timeRanges'] ?? [];
        $filters = $data['filters'] ?? [];

        $view->with([
            'timeRanges' => $timeRanges,
            'filters' => $filters,
        ]);
    }
}
