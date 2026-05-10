<?php

declare(strict_types=1);

namespace App\Support\UI;

use Illuminate\Support\Facades\Route;

/**
 * Breadcrumb Builder
 * Zentraler Builder für konsistente Breadcrumb-Hierarchien.
 *
 * SOLID: Single Responsibility - Nur Breadcrumb-Generierung
 * DDD: Application Service - Layout-relevante Orchestrierung
 *
 * Unterstützt:
 * - 3-Level-Hierarchie: Home → Section → Page
 * - 4-Level-Hierarchie für masterdata-Submodule: Home → Stammdaten → SubModule → Page
 * - Dynamische Titel für Detail-Pages (orders/show, *_edit, *_show)
 * - Parent-Link für Detail-Pages
 */
final class BreadcrumbBuilder
{
    private const HOME_LABEL = 'Startseite';
    private const HOME_ROUTE = 'dispatch-lists';

    /**
     * Masterdata-Submodule mit 4-Level-Hierarchie.
     *
     * @var array<string, string>
     */
    private const MASTERDATA_SUBMODULES = [
        'packaging' => 'Verpackungsprofile',
        'assembly' => 'Konfigurationsoptionen',
        'variations' => 'Variantenprofile',
        'senders' => 'Absenderprofile',
        'sender-rules' => 'Absenderregeln',
        'freight' => 'Frachtprofile',
    ];

    /**
     * Route-Präfixe für Masterdata-Submodule.
     *
     * @var array<string, string>
     */
    private const MASTERDATA_ROUTE_PREFIXES = [
        'fulfillment.masterdata.packaging' => 'packaging',
        'fulfillment.masterdata.assembly' => 'assembly',
        'fulfillment.masterdata.variations' => 'variations',
        'fulfillment.masterdata.senders' => 'senders',
        'fulfillment.masterdata.sender-rules' => 'sender-rules',
        'fulfillment.masterdata.freight' => 'freight',
    ];

    /**
     * Sektionen mit korrekten Breadcrumb-Labels.
     *
     * @var array<string, array{label: string, route: string}>
     */
    private const SECTION_MAP = [
        'fulfillment' => ['label' => 'DHL Fulfillment', 'route' => 'dispatch-lists'],
        'fulfillment-orders' => ['label' => 'DHL Fulfillment', 'route' => 'dispatch-lists'],
        'fulfillment-orders.show' => ['label' => 'DHL Fulfillment', 'route' => 'dispatch-lists'],
        'fulfillment-masterdata' => ['label' => 'Stammdaten', 'route' => 'fulfillment-masterdata'],
        'fulfillment.masterdata.packaging' => ['label' => 'Stammdaten', 'route' => 'fulfillment-masterdata'],
        'fulfillment.masterdata.assembly' => ['label' => 'Stammdaten', 'route' => 'fulfillment-masterdata'],
        'fulfillment.masterdata.variations' => ['label' => 'Stammdaten', 'route' => 'fulfillment-masterdata'],
        'fulfillment.masterdata.senders' => ['label' => 'Stammdaten', 'route' => 'fulfillment-masterdata'],
        'fulfillment.masterdata.sender-rules' => ['label' => 'Stammdaten', 'route' => 'fulfillment-masterdata'],
        'fulfillment.masterdata.freight' => ['label' => 'Stammdaten', 'route' => 'fulfillment-masterdata'],
        'fulfillment-shipments' => ['label' => 'DHL Fulfillment', 'route' => 'dispatch-lists'],
        'csv-export' => ['label' => 'DHL Fulfillment', 'route' => 'dispatch-lists'],
        'dispatch-lists' => ['label' => 'Dispatch', 'route' => 'dispatch-lists'],
        'monitoring-health' => ['label' => 'Monitoring', 'route' => 'monitoring-system-jobs'],
        'monitoring-logs' => ['label' => 'Monitoring', 'route' => 'monitoring-system-jobs'],
        'tracking-overview' => ['label' => 'Tracking', 'route' => 'tracking-overview'],
        'tracking-jobs.show' => ['label' => 'Tracking', 'route' => 'tracking-overview'],
        'tracking-alerts.show' => ['label' => 'Tracking', 'route' => 'tracking-overview'],
        'monitoring-audit-logs' => ['label' => 'Monitoring', 'route' => 'monitoring-system-jobs'],
        'monitoring-system-jobs' => ['label' => 'Monitoring', 'route' => 'monitoring-system-jobs'],
        'monitoring-domain-events' => ['label' => 'Monitoring', 'route' => 'monitoring-system-jobs'],
        'identity-users' => ['label' => 'Identität', 'route' => 'identity-users'],
        'identity-users.show' => ['label' => 'Identität', 'route' => 'identity-users'],
        'identity-users.create' => ['label' => 'Identität', 'route' => 'identity-users'],
        'identity-users.edit' => ['label' => 'Identität', 'route' => 'identity-users'],
        'configuration-settings' => ['label' => 'Konfiguration', 'route' => 'configuration-settings'],
        'configuration-settings.create' => ['label' => 'Konfiguration', 'route' => 'configuration-settings'],
        'configuration-settings.edit' => ['label' => 'Konfiguration', 'route' => 'configuration-settings'],
        'configuration-mail-templates' => ['label' => 'Konfiguration', 'route' => 'configuration-settings'],
        'configuration-mail-templates.create' => ['label' => 'Konfiguration', 'route' => 'configuration-settings'],
        'configuration-mail-templates.edit' => ['label' => 'Konfiguration', 'route' => 'configuration-settings'],
        'configuration-notifications' => ['label' => 'Konfiguration', 'route' => 'configuration-settings'],
        'configuration-integrations' => ['label' => 'Integrationen', 'route' => 'configuration-integrations'],
        'configuration-integrations.show' => ['label' => 'Integrationen', 'route' => 'configuration-integrations'],
    ];

    /**
     * Action-Labels für dynamische Seitentitel.
     *
     * @var array<string, string>
     */
    private const ACTION_LABELS = [
        'index' => 'Übersicht',
        'create' => 'Erstellen',
        'edit' => 'Bearbeiten',
        'show' => 'Details',
        'store' => 'Erstellen',
        'update' => 'Aktualisieren',
        'destroy' => 'Löschen',
    ];

    /**
     * Erstellt eine Breadcrumb-Hierarchie.
     *
     * @param  string  $currentPage  Aktueller Seitentitel oder Action-Name
     * @param  string|null  $parentRoute  Routenname für Parent-Link (z.B. 'fulfillment-orders' für orders/show)
     * @param  array<int, array{label: string, url: string|null}>  $extraCrumbs  Zusätzliche Crumbs zwischen Home und aktueller Seite
     * @return array<int, array{label: string, url: string|null}>
     */
    public function build(string $currentPage, ?string $parentRoute = null, array $extraCrumbs = []): array
    {
        $currentRouteName = Route::currentRouteName() ?? '';

        // Bestimme die Hierarchie-Tiefe
        $depth = $this->determineDepth($currentRouteName, ! empty($extraCrumbs));

        if ($depth === 4) {
            return $this->buildFourLevel($currentPage, $currentRouteName);
        }

        if ($depth === 3 || ! empty($extraCrumbs)) {
            return $this->buildThreeLevel($currentPage, $parentRoute, $extraCrumbs);
        }

        return $this->buildFallback($currentPage);
    }

    /**
     * Bestimmt die Hierarchie-Tiefe basierend auf Route.
     */
    private function determineDepth(string $routeName, bool $hasExtraCrumbs): int
    {
        // Masterdata-Submodule immer 4-Level
        foreach (self::MASTERDATA_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, 'fulfillment.masterdata.')) {
                return 4;
            }
        }

        return $hasExtraCrumbs ? 3 : 2;
    }

    /**
     * Erstellt 4-Level-Hierarchie für Masterdata-Submodule.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    private function buildFourLevel(string $currentPage, string $routeName): array
    {
        $submoduleKey = $this->detectMasterdataSubmodule($routeName);
        $submoduleLabel = self::MASTERDATA_SUBMODULES[$submoduleKey] ?? 'Stammdaten';
        $submoduleIndexRoute = $this->getMasterdataSubmoduleRoute($submoduleKey);

        $currentAction = $this->detectAction($routeName);
        $currentLabel = $this->resolvePageLabel($currentPage, $currentAction);

        $items = [
            0 => ['label' => self::HOME_LABEL, 'url' => $this->routeUrl(self::HOME_ROUTE)],
            1 => ['label' => 'Stammdaten', 'url' => $this->routeUrl('fulfillment-masterdata')],
            2 => ['label' => $submoduleLabel, 'url' => $this->routeUrl($submoduleIndexRoute)],
            3 => ['label' => $currentLabel, 'url' => null],
        ];

        // Bei create/edit/edit: Parent-Link auf Index setzen
        if (in_array($currentAction, ['create', 'edit'], true)) {
            $items[3]['url'] = $this->routeUrl($submoduleIndexRoute);
        }

        return $items;
    }

    /**
     * Erstellt 3-Level-Hierarchie.
     *
     * @param  string|null  $parentRoute
     * @param  array<int, array{label: string, url: string|null}>  $extraCrumbs
     * @return array<int, array{label: string, url: string|null}>
     */
    private function buildThreeLevel(string $currentPage, ?string $parentRoute, array $extraCrumbs): array
    {
        $homeCrumb = ['label' => self::HOME_LABEL, 'url' => $this->routeUrl(self::HOME_ROUTE)];

        // Extra Crumbs (z.B. für masterdata-Submodule bei create/edit)
        if (! empty($extraCrumbs)) {
            $currentAction = $this->detectAction(Route::currentRouteName() ?? '');
            $currentLabel = $this->resolvePageLabel($currentPage, $currentAction);

            $items = array_merge([$homeCrumb], $extraCrumbs);
            $lastExtra = end($extraCrumbs);

            // Parent-Link für Detail-Pages
            if (str_ends_with($currentAction, 'edit') || str_ends_with($currentAction, 'show')) {
                $items[] = ['label' => $currentLabel, 'url' => $lastExtra['url']];
            } else {
                $items[] = ['label' => $currentLabel, 'url' => null];
            }

            return $items;
        }

        // Parent-basiert
        if ($parentRoute !== null) {
            $sectionInfo = $this->resolveSection($parentRoute);
            $currentAction = $this->detectAction(Route::currentRouteName() ?? '');
            $currentLabel = $this->resolvePageLabel($currentPage, $currentAction);

            $items = [
                $homeCrumb,
                ['label' => $sectionInfo['label'], 'url' => $this->routeUrl($sectionInfo['route'])],
                ['label' => $currentLabel, 'url' => null],
            ];

            // Parent-Link für Detail-Pages
            if (str_ends_with($currentAction, 'edit') || str_ends_with($currentAction, 'show')) {
                $items[2]['url'] = $this->routeUrl($sectionInfo['route']);
            }

            return $items;
        }

        return $this->buildFallback($currentPage);
    }

    /**
     * Fallback: Home + aktuelle Seite.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    private function buildFallback(string $currentPage): array
    {
        return [
            ['label' => self::HOME_LABEL, 'url' => $this->routeUrl(self::HOME_ROUTE)],
            ['label' => $currentPage, 'url' => null],
        ];
    }

    /**
     * Erkennt das Masterdata-Submodule aus der Route.
     */
    private function detectMasterdataSubmodule(string $routeName): string
    {
        foreach (self::MASTERDATA_ROUTE_PREFIXES as $prefix => $key) {
            if (str_starts_with($routeName, $prefix)) {
                return $key;
            }
        }

        return 'packaging';
    }

    /**
     * Gibt die Index-Route für ein Masterdata-Submodule zurück.
     */
    private function getMasterdataSubmoduleRoute(string $submoduleKey): string
    {
        $mapping = [
            'packaging' => 'fulfillment.masterdata.packaging.index',
            'assembly' => 'fulfillment.masterdata.assembly.index',
            'variations' => 'fulfillment.masterdata.variations.index',
            'senders' => 'fulfillment.masterdata.senders.index',
            'sender-rules' => 'fulfillment.masterdata.sender-rules.index',
            'freight' => 'fulfillment.masterdata.freight.index',
        ];

        return $mapping[$submoduleKey] ?? 'fulfillment-masterdata';
    }

    /**
     * Erkennt die Action aus dem Route-Namen.
     */
    private function detectAction(string $routeName): string
    {
        $parts = explode('.', $routeName);

        return end($parts);
    }

    /**
     * Löst den Seitentitel auf.
     */
    private function resolvePageLabel(string $currentPage, string $action): string
    {
        // Kein expliziter Titel angegeben
        if ($currentPage === '') {
            return self::ACTION_LABELS[$action] ?? ucfirst($action);
        }

        // Titel enthält bereits aussagekräftigen Inhalt (z.B. Entity-Name)
        if (mb_strlen($currentPage) > 2 && ! in_array($currentPage, array_values(self::ACTION_LABELS), true)) {
            return $currentPage;
        }

        // Kurzform → via Action-Label auflösen
        return self::ACTION_LABELS[$action] ?? ucfirst($action);
    }

    /**
     * Löst die Sektion basierend auf Parent-Route auf.
     *
     * @return array{label: string, route: string}
     */
    private function resolveSection(string $parentRoute): array
    {
        return self::SECTION_MAP[$parentRoute] ?? [
            'label' => 'Übersicht',
            'route' => $parentRoute,
        ];
    }

    /**
     * Generiert eine URL für einen Routennamen.
     */
    private function routeUrl(string $routeName, array $parameters = []): ?string
    {
        if (! Route::has($routeName)) {
            return null;
        }

        try {
            return route($routeName, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * shortcut für build() - einfacherer Interface-Aufruf.
     *
     * @param  string  $currentPage
     * @param  string|null  $parentRoute
     * @param  array<int, array{label: string, url: string|null}>  $extraCrumbs
     * @return array<int, array{label: string, url: string|null}>
     */
    public function __invoke(string $currentPage, ?string $parentRoute = null, array $extraCrumbs = []): array
    {
        return $this->build($currentPage, $parentRoute, $extraCrumbs);
    }
}