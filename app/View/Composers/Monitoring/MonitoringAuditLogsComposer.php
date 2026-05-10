<?php

declare(strict_types=1);

namespace App\View\Composers\Monitoring;

use Illuminate\View\View;

/**
 * Monitoring Audit Logs View Composer
 * Bereitet Audit-Logs-Index-Daten vor
 */
final class MonitoringAuditLogsComposer
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
