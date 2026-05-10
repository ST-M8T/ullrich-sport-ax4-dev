<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\View\View;

/**
 * Configuration Settings System Logs View Composer
 * Bereitet System-Logs-Section-Daten vor
 */
final class ConfigurationSettingsSystemLogsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['systemStatus'])) {
            return;
        }

        /** @var array<string, mixed> $systemStatus */
        $systemStatus = $data['systemStatus'];
        /** @var array<string, mixed> $logMeta */
        $logMeta = $systemStatus['logs'] ?? [];
        /** @var iterable<int|string, mixed> $rawDirectories */
        $rawDirectories = $logMeta['directories'] ?? [];
        $directories = collect($rawDirectories);

        $view->with([
            'logMeta' => $logMeta,
            'directories' => $directories,
            'defaultChannel' => $logMeta['default_channel'] ?? config('logging.default'),
        ]);
    }
}
