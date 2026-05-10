<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\View\View;

/**
 * Configuration Settings System Status View Composer
 * Bereitet System-Status-Section-Daten vor
 */
final class ConfigurationSettingsSystemStatusComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['systemStatus'])) {
            return;
        }

        /** @var array<string, mixed> $systemStatus */
        $systemStatus = $data['systemStatus'];

        $health = strtoupper($systemStatus['health']['status'] ?? 'UNKNOWN');
        $healthTone = $health === 'OK' ? 'ok' : ($health === 'WARN' ? 'warn' : 'info');
        /** @var array<int|string, array<string, mixed>> $configuration */
        $configuration = $systemStatus['configuration']['settings'] ?? [];
        $configured = collect($configuration)->filter(fn ($setting) => $setting['is_configured'] ?? false)->count();
        $queueTotal = $systemStatus['queue']['total'] ?? 0;
        $logDirectories = $systemStatus['logs']['directories'] ?? [];

        $view->with([
            'health' => $health,
            'healthTone' => $healthTone,
            'configuration' => $configuration,
            'configured' => $configured,
            'queueTotal' => $queueTotal,
            'logDirectories' => $logDirectories,
        ]);
    }
}
