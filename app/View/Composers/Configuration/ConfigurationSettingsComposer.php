<?php

declare(strict_types=1);

namespace App\View\Composers\Configuration;

use Illuminate\View\View;

/**
 * Configuration Settings View Composer
 * Bereitet Daten für die Settings-Index-View vor
 * SOLID: Single Responsibility - Nur Settings-Daten vorbereiten
 * DDD: Presentation Layer - View-spezifische Daten
 */
final class ConfigurationSettingsComposer
{
    /**
     * Bindet Settings-Daten an View
     */
    public function compose(View $view): void
    {
        $data = $view->getData();

        $groupSlugs = array_keys($data['groups'] ?? []);
        $groupTabParam = 'settings_group';
        $requestedGroup = request()->query($groupTabParam);
        $activeGroup = in_array($requestedGroup, $groupSlugs, true) ? $requestedGroup : ($groupSlugs[0] ?? null);
        $settingsTabLabel = $activeGroup !== null
            ? ($data['groups'][$activeGroup]['label'] ?? 'Konfiguration')
            : 'Konfiguration';

        $primaryTabs = [
            'settings' => ['label' => $settingsTabLabel, 'visible' => ! empty($data['groups'])],
            'masterdata' => ['label' => 'Stammdaten', 'visible' => isset($data['catalog'])],
            'monitoring' => ['label' => 'Monitoring', 'visible' => true],
            'logs' => ['label' => 'Logs', 'visible' => true],
            'verwaltung' => ['label' => 'Verwaltung', 'visible' => true],
        ];
        $primaryTabs = array_filter($primaryTabs, fn ($tab) => $tab['visible']);
        $requestedTab = request()->query('tab');
        $activeTab = array_key_exists($requestedTab, $primaryTabs) ? $requestedTab : (array_key_first($primaryTabs) ?? 'settings');

        $monitoringLinks = [
            ['key' => 'system-status', 'label' => 'Systemstatus', 'description' => 'Health-Checks, Queue-Zustand und System-Metriken.', 'route' => 'monitoring-health', 'permission' => 'admin.setup.view'],
            ['key' => 'system-jobs', 'label' => 'System-Jobs', 'description' => 'Job-Läufe beobachten und Fehler analysieren.', 'route' => 'monitoring-system-jobs', 'permission' => 'monitoring.system_jobs.view'],
            ['key' => 'tracking', 'label' => 'Tracking-Übersicht', 'description' => 'Monitoring für Tracking-Jobs und Alerts.', 'route' => 'tracking-overview', 'permission' => 'tracking.overview.view'],
        ];

        $verwaltungLinks = [
            ['key' => 'identity-users', 'label' => 'Benutzer & Rollen', 'description' => 'Admin-Konten und Rollen verwalten.', 'route' => 'identity-users', 'permission' => 'identity.users.manage'],
            ['key' => 'notifications', 'label' => 'Benachrichtigungs-Regeln', 'description' => 'Regeln für interne/externe E-Mail Alerts verwalten.', 'route' => 'configuration-notifications', 'permission' => 'configuration.notifications.manage'],
        ];

        $logToolLinks = [
            ['key' => 'system-logs', 'label' => 'System-Logs', 'description' => 'Logfiles downloaden oder einsehen.', 'route' => 'monitoring-logs', 'permission' => 'admin.logs.view'],
            ['key' => 'audit-logs', 'label' => 'Audit-Logs', 'description' => 'Änderungshistorie und Nutzeraktionen nachvollziehen.', 'route' => 'monitoring-audit-logs', 'permission' => 'monitoring.audit_logs.view'],
            ['key' => 'domain-events', 'label' => 'Domain-Events', 'description' => 'Event-Stream für Integrationen prüfen.', 'route' => 'monitoring-domain-events', 'permission' => 'monitoring.domain_events.view'],
        ];

        $monitoringViews = [
            'system-status' => [
                'view' => 'configuration.settings.partials.monitoring.sections.system-status',
                'data' => ['systemStatus' => $data['systemStatus'] ?? null],
            ],
            'system-jobs' => [
                'view' => 'configuration.settings.partials.monitoring.sections.system-jobs',
                'data' => ['systemStatus' => $data['systemStatus'] ?? null],
            ],
            'tracking' => [
                'view' => 'configuration.settings.partials.monitoring.sections.tracking',
            ],
        ];

        $verwaltungViews = [
            'identity-users' => [
                'view' => 'configuration.settings.partials.verwaltung.sections.identity-users',
            ],
            'notifications' => [
                'view' => 'configuration.settings.partials.verwaltung.sections.notifications',
            ],
        ];

        $logViews = [
            'audit-logs' => [
                'view' => 'configuration.settings.partials.logs.sections.audit-logs',
            ],
            'domain-events' => [
                'view' => 'configuration.settings.partials.logs.sections.domain-events',
            ],
            'system-logs' => [
                'view' => 'configuration.settings.partials.logs.sections.system-logs',
                'data' => ['systemStatus' => $data['systemStatus'] ?? null],
            ],
        ];

        $availableMonitoring = collect($monitoringLinks)
            ->filter(fn (array $item): bool => empty($item['permission']) || \Illuminate\Support\Facades\Gate::allows($item['permission']))
            ->map(function (array $item) use ($monitoringViews) {
                if (isset($monitoringViews[$item['key']])) {
                    $item['view'] = $monitoringViews[$item['key']]['view'];
                    $item['view_data'] = $monitoringViews[$item['key']]['data'] ?? [];
                }

                return $item;
            })
            ->values()
            ->all();

        $availableVerwaltung = collect($verwaltungLinks)
            ->filter(fn (array $item): bool => empty($item['permission']) || \Illuminate\Support\Facades\Gate::allows($item['permission']))
            ->map(function (array $item) use ($verwaltungViews) {
                if (isset($verwaltungViews[$item['key']])) {
                    $item['view'] = $verwaltungViews[$item['key']]['view'];
                    // Verwaltung-Views liefern derzeit keine vor-aufbereiteten View-Daten;
                    // die Blade-Templates fallen via `?? []` auf einen leeren Default zurück.
                }

                return $item;
            })
            ->values()
            ->all();

        $availableLogTools = collect($logToolLinks)
            ->filter(fn (array $item): bool => empty($item['permission']) || \Illuminate\Support\Facades\Gate::allows($item['permission']))
            ->map(function (array $item) use ($logViews) {
                if (isset($logViews[$item['key']])) {
                    $item['view'] = $logViews[$item['key']]['view'];
                    $item['view_data'] = $logViews[$item['key']]['data'] ?? [];
                }

                return $item;
            })
            ->values()
            ->all();

        $monitoringSortOrder = ['system-status', 'system-jobs', 'tracking'];
        $availableMonitoring = collect($availableMonitoring)
            ->sortBy(fn (array $item) => array_search($item['key'], $monitoringSortOrder, true) !== false ? array_search($item['key'], $monitoringSortOrder, true) : 999)
            ->values()
            ->all();

        $verwaltungSortOrder = ['identity-users', 'notifications'];
        $availableVerwaltung = collect($availableVerwaltung)
            ->sortBy(fn (array $item) => array_search($item['key'], $verwaltungSortOrder, true) !== false ? array_search($item['key'], $verwaltungSortOrder, true) : 999)
            ->values()
            ->all();

        $primaryTabs['settings'] = ['label' => $settingsTabLabel, 'visible' => ! empty($data['groups'])];
        $primaryTabs['monitoring'] = ['label' => 'Monitoring', 'visible' => ! empty($availableMonitoring)];
        $primaryTabs['logs'] = ['label' => 'Logs', 'visible' => ! empty($availableLogTools)];
        $primaryTabs['verwaltung'] = ['label' => 'Verwaltung', 'visible' => ! empty($availableVerwaltung)];
        $primaryTabs = array_filter($primaryTabs, fn ($tab) => $tab['visible']);
        $activeTab = array_key_exists($requestedTab, $primaryTabs) ? $requestedTab : (array_key_first($primaryTabs) ?? 'settings');

        $view->with([
            'groupTabParam' => $groupTabParam,
            'activeGroup' => $activeGroup,
            'primaryTabs' => $primaryTabs,
            'activeTab' => $activeTab,
            'availableMonitoring' => $availableMonitoring,
            'availableLogTools' => $availableLogTools,
            'availableVerwaltung' => $availableVerwaltung,
            'notifications' => $data['notifications'] ?? [],
            'users' => $data['users'] ?? [],
            'roleOptions' => $data['roleOptions'] ?? [],
        ]);
    }
}
