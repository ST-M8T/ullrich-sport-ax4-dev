<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Configuration Settings Logs View Composer
 * Bereitet Logs-Partial-Daten vor
 */
final class ConfigurationSettingsLogsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        /** @var array<int, array<string, mixed>> $availableTools */
        $availableTools = $data['availableTools'] ?? [];

        $logsSortOrder = ['system-logs', 'audit-logs', 'domain-events'];
        $availableTools = collect($availableTools)
            ->sortBy(fn (array $tool) => array_search($tool['key'], $logsSortOrder, true) !== false ? array_search($tool['key'], $logsSortOrder, true) : 999)
            ->values()
            ->all();

        $activeLogTab = request()->query('log_tab', $availableTools[0]['key'] ?? null);

        $logTabs = collect($availableTools)->mapWithKeys(function ($tool) {
            return [$tool['key'] => ['label' => $tool['label']]];
        })->all();

        $processedTools = [];
        foreach ($availableTools as $tool) {
            $toolRoute = isset($tool['route']) && Route::has($tool['route'])
                ? route($tool['route'])
                : null;

            $processedTools[] = array_merge($tool, [
                'route' => $toolRoute,
            ]);
        }

        $view->with([
            'processedTools' => $processedTools,
            'activeLogTab' => $activeLogTab,
            'logTabs' => $logTabs,
        ]);
    }
}
