<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Configuration Settings Verwaltung View Composer
 * Bereitet Verwaltung-Partial-Daten vor
 */
final class ConfigurationSettingsVerwaltungComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        /** @var array<int, array<string, mixed>> $availableVerwaltung */
        $availableVerwaltung = $data['availableVerwaltung'] ?? [];

        $activeVerwaltung = request()->query('verwaltung_tab', $availableVerwaltung[0]['key'] ?? null);

        $verwaltungTabs = collect($availableVerwaltung)->mapWithKeys(function ($item) {
            return [$item['key'] => ['label' => $item['label']]];
        })->all();

        $processedVerwaltung = [];
        foreach ($availableVerwaltung as $item) {
            $itemRoute = isset($item['route']) && Route::has($item['route'])
                ? route($item['route'])
                : null;

            $processedVerwaltung[] = array_merge($item, [
                'route' => $itemRoute,
            ]);
        }

        $view->with([
            'processedVerwaltung' => $processedVerwaltung,
            'activeVerwaltung' => $activeVerwaltung,
            'verwaltungTabs' => $verwaltungTabs,
            'notifications' => $data['notifications'] ?? [],
            'users' => $data['users'] ?? [],
            'roleOptions' => $data['roleOptions'] ?? [],
        ]);
    }
}
