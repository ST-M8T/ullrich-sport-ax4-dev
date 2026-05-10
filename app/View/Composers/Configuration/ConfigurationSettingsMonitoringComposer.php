<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Configuration Settings Monitoring View Composer
 * Bereitet Monitoring-Partial-Daten vor
 */
final class ConfigurationSettingsMonitoringComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        /** @var array<int, array<string, mixed>> $availableMonitoring */
        $availableMonitoring = $data['availableMonitoring'] ?? [];

        $activeMonitoring = request()->query('monitoring_tab', $availableMonitoring[0]['key'] ?? null);

        $monitoringTabs = collect($availableMonitoring)->mapWithKeys(function ($item) {
            return [$item['key'] => ['label' => $item['label']]];
        })->all();

        $processedMonitoring = [];
        foreach ($availableMonitoring as $item) {
            $itemRoute = isset($item['route']) && Route::has($item['route'])
                ? route($item['route'])
                : null;

            $processedMonitoring[] = array_merge($item, [
                'route' => $itemRoute,
            ]);
        }

        $view->with([
            'processedMonitoring' => $processedMonitoring,
            'activeMonitoring' => $activeMonitoring,
            'monitoringTabs' => $monitoringTabs,
        ]);
    }
}
