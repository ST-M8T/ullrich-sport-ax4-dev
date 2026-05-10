<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\View\View;

/**
 * Configuration Settings System Jobs View Composer
 * Bereitet System-Jobs-Section-Daten vor
 */
final class ConfigurationSettingsSystemJobsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['systemStatus'])) {
            return;
        }

        /** @var array<string, mixed> $systemStatus */
        $systemStatus = $data['systemStatus'];
        /** @var array<string, mixed> $queue */
        $queue = $systemStatus['queue'] ?? [];
        $counts = $queue['counts'] ?? [];
        /** @var iterable<int, array<string, mixed>> $recent */
        $recent = $queue['recent'] ?? [];
        $recentJobs = collect($recent)->take(8);

        $statusLabels = [
            'pending' => 'Geplant',
            'running' => 'Laufend',
            'failed' => 'Fehlgeschlagen',
            'succeeded' => 'Erfolgreich',
        ];

        $view->with([
            'counts' => $counts,
            'recentJobs' => $recentJobs,
            'statusLabels' => $statusLabels,
        ]);
    }
}
