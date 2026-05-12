<?php

declare(strict_types=1);

namespace App\Support\UI;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Navigation Service
 *
 * Verwaltet zwei-stufige Navigation: Top-Level-Gruppen (Container) mit Sub-Items.
 *
 * SOLID: Single Responsibility - Nur Navigation-Verwaltung.
 * DDD: Application Service - orchestriert Sichtbarkeits- und Aktiv-Logik fuer
 * den Praesentationslayer.
 *
 * Datenmodell:
 *  - Container: hat 'key', 'label', 'children' (>=1 Sub-Items), KEINE eigene
 *    Route, KEINE eigene Permission. Sichtbarkeit = OR ueber sichtbare Kinder.
 *  - Sub-Item (Leaf): hat 'key', 'label', 'route' und genau eine 'permissions'-
 *    Liste mit mindestens einem Eintrag. Sichtbarkeit = `Gate::allows(...)`.
 *
 * Fail-Closed: Sub-Items ohne Permission werfen InvalidArgumentException
 * (Engineering-Handbuch Section 19, 67). Container muessen >= 1 Child haben.
 */
final class NavigationService
{
    /**
     * Standard-Navigation: 5 Top-Level-Gruppen mit insgesamt 17 Sub-Items.
     *
     * Quelle: t14-Mockup (AX4 Wave 9). Permissions referenzieren config/identity.php.
     * Stand Wave 14: Tracking-Gruppe erweitert um Jobs + Alerts (3 Sub-Items);
     * IntegrationPolicy gesplittet (configuration.integrations.manage).
     *
     * Stand t22: Stammdaten + Verwaltung zusammengefasst zu "Stammdaten & Benutzer";
     * Monitoring umbenannt zu "System".
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDefaultItems(): array
    {
        return [
            [
                'key' => 'operations',
                'label' => 'Operations',
                'children' => [
                    [
                        'key' => 'fulfillment-orders',
                        'label' => 'Aufträge',
                        'route' => 'fulfillment-orders',
                        'permissions' => ['fulfillment.orders.view'],
                    ],
                    [
                        'key' => 'fulfillment-shipments',
                        'label' => 'Sendungen',
                        'route' => 'fulfillment-shipments',
                        'permissions' => ['fulfillment.shipments.manage'],
                    ],
                    [
                        'key' => 'dispatch-lists',
                        'label' => 'Kommissionierlisten',
                        'route' => 'dispatch-lists',
                        'permissions' => ['dispatch.lists.manage'],
                    ],
                    [
                        'key' => 'csv-export',
                        'label' => 'CSV-Export',
                        'route' => 'csv-export',
                        'permissions' => ['fulfillment.csv_export.manage'],
                    ],
                ],
            ],
            [
                'key' => 'stammdaten-benutzer',
                'label' => 'Stammdaten & Benutzer',
                'children' => [
                    [
                        'key' => 'fulfillment-masterdata',
                        'label' => 'Fulfillment-Stammdaten',
                        'route' => 'fulfillment-masterdata',
                        'permissions' => ['fulfillment.masterdata.manage'],
                    ],
                    [
                        'key' => 'identity-users',
                        'label' => 'Benutzer',
                        'route' => 'identity-users',
                        'permissions' => ['identity.users.manage'],
                    ],
                ],
            ],
            [
                'key' => 'tracking',
                'label' => 'Tracking',
                'children' => [
                    [
                        'key' => 'tracking-overview',
                        'label' => 'Übersicht',
                        'route' => 'tracking-overview',
                        'permissions' => ['tracking.overview.view'],
                    ],
                    [
                        'key' => 'tracking-jobs',
                        'label' => 'Jobs',
                        'route' => 'tracking-jobs.show',
                        'permissions' => ['tracking.jobs.manage'],
                    ],
                    [
                        'key' => 'tracking-alerts',
                        'label' => 'Alerts',
                        'route' => 'tracking-alerts.show',
                        'permissions' => ['tracking.alerts.manage'],
                    ],
                ],
            ],
            [
                'key' => 'system',
                'label' => 'System',
                'children' => [
                    [
                        'key' => 'monitoring-system-jobs',
                        'label' => 'System-Jobs',
                        'route' => 'monitoring-system-jobs',
                        'permissions' => ['monitoring.system_jobs.view'],
                    ],
                    [
                        'key' => 'monitoring-domain-events',
                        'label' => 'Domain Events',
                        'route' => 'monitoring-domain-events',
                        'permissions' => ['monitoring.domain_events.view'],
                    ],
                    [
                        'key' => 'monitoring-audit-logs',
                        'label' => 'Audit-Logs',
                        'route' => 'monitoring-audit-logs',
                        'permissions' => ['monitoring.audit_logs.view'],
                    ],
                    [
                        'key' => 'monitoring-logs',
                        'label' => 'System-Logs',
                        'route' => 'monitoring-logs',
                        'permissions' => ['admin.logs.view'],
                    ],
                    [
                        'key' => 'monitoring-health',
                        'label' => 'System-Health',
                        'route' => 'monitoring-health',
                        'permissions' => ['admin.setup.view'],
                    ],
                ],
            ],
            [
                'key' => 'konfiguration',
                'label' => 'Konfiguration',
                'children' => [
                    [
                        'key' => 'configuration-settings',
                        'label' => 'Systemeinstellungen',
                        'route' => 'configuration-settings',
                        'permissions' => ['configuration.settings.manage'],
                    ],
                    [
                        'key' => 'configuration-mail-templates',
                        'label' => 'Mail-Vorlagen',
                        'route' => 'configuration-mail-templates',
                        'permissions' => ['configuration.mail_templates.manage'],
                    ],
                    [
                        'key' => 'configuration-notifications',
                        'label' => 'Benachrichtigungen',
                        'route' => 'configuration-notifications',
                        'permissions' => ['configuration.notifications.manage'],
                    ],
                    [
                        'key' => 'configuration-integrations',
                        'label' => 'Integrationen',
                        'route' => 'configuration-integrations',
                        'permissions' => ['configuration.integrations.manage'],
                    ],
                    [
                        'key' => 'admin-settings-dhl-freight',
                        'label' => 'Versand: DHL Freight',
                        'route' => 'admin.settings.dhl-freight.index',
                        'permissions' => ['settings.dhl_freight.manage'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Normalisiert Navigation-Items.
     *
     * Container (mit 'children'): keine eigene Permission noetig, muss aber
     * >= 1 normalisierbares Sub-Item enthalten. Eigene 'route' wird ignoriert.
     *
     * Sub-Items (Leaf): muessen explizit Permission(s) deklarieren. Items
     * ohne Permission werden als Konfigurationsfehler abgewiesen
     * (Fail-Fast, Engineering-Handbuch Section 67).
     *
     * @param  array<int|string, mixed>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    public function normalizeItems(array $rawItems): array
    {
        return collect($rawItems)
            ->map(fn ($value, $key) => $this->normalizeItem($value, $key))
            ->filter(fn ($item) => filled($item['label']))
            ->values()
            ->all();
    }

    /**
     * Normalisiert genau ein Item — entweder Container oder Leaf.
     *
     * @return array<string, mixed>
     */
    private function normalizeItem(mixed $value, int|string $key): array
    {
        if (! is_array($value)) {
            $label = (string) $value;
            $itemKey = is_string($key) ? $key : Str::slug($label);

            throw new InvalidArgumentException(sprintf(
                'Navigation-Item "%s" muss als Array mit mindestens einer Permission deklariert werden.',
                $itemKey !== '' ? $itemKey : 'unbenannt'
            ));
        }

        $label = $value['label'] ?? $value['text'] ?? (is_string($key) ? $key : '');
        $itemKey = $value['key'] ?? (is_string($key) ? $key : Str::slug((string) $label));

        if ($this->isContainer($value)) {
            $children = $this->normalizeItems($value['children']);

            if ($children === []) {
                throw new InvalidArgumentException(sprintf(
                    'Navigation-Container "%s" muss mindestens ein Sub-Item enthalten.',
                    $itemKey !== '' ? (string) $itemKey : 'unbenannt'
                ));
            }

            return [
                'key' => $itemKey,
                'label' => $label,
                'children' => $children,
            ];
        }

        $permissions = $this->extractPermissions($value);

        if ($permissions === []) {
            throw new InvalidArgumentException(sprintf(
                'Navigation-Item "%s" muss mindestens eine Permission deklarieren (permission|permissions).',
                is_string($key) ? $key : ((string) ($value['key'] ?? $label))
            ));
        }

        return [
            'key' => $itemKey,
            'label' => $label,
            'route' => $value['route'] ?? ($value['key'] ?? (is_string($key) ? $key : null)),
            'href' => $value['href'] ?? null,
            'permissions' => $permissions,
            'active' => $value['active'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function isContainer(array $value): bool
    {
        return isset($value['children']) && is_array($value['children']);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<int, string>
     */
    private function extractPermissions(array $value): array
    {
        $permissions = [];

        if (isset($value['permission']) && is_string($value['permission'])) {
            $permissions[] = trim($value['permission']);
        }

        if (isset($value['permissions']) && is_array($value['permissions'])) {
            foreach ($value['permissions'] as $permission) {
                if (is_string($permission)) {
                    $permissions[] = trim($permission);
                }
            }
        }

        return array_values(array_unique(array_filter($permissions)));
    }

    /**
     * Prueft ob ein Benutzer die Berechtigung fuer ein Sub-Item hat.
     *
     * Fail-Closed (Engineering-Handbuch Section 19, 67): Eine leere
     * Permission-Liste signalisiert ein fehlkonfiguriertes Item und wird
     * grundsaetzlich verweigert.
     *
     * @param  array<int, string>  $permissions
     */
    public function hasPermission(array $permissions): bool
    {
        if (empty($permissions)) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (Gate::allows($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Erstellt URL fuer ein Sub-Item (Leaf).
     *
     * @param  array<string, mixed>  $item
     */
    public function buildUrl(array $item): ?string
    {
        $routeName = $item['route'] ?? $item['key'] ?? null;
        $href = $item['href'] ?? null;

        if ($routeName && $href === null) {
            try {
                if (Route::has($routeName)) {
                    return route($routeName);
                }
            } catch (\Exception $e) {
                return null;
            }
        }

        return $href;
    }

    /**
     * Prueft ob ein Sub-Item aktiv ist.
     *
     * @param  array<string, mixed>  $item
     */
    public function isActive(array $item, string $currentSection): bool
    {
        $isActive = $item['active'] ?? null;
        if ($isActive !== null) {
            return (bool) $isActive;
        }

        $routeName = $item['route'] ?? $item['key'] ?? null;
        if ($routeName && request()->route()) {
            if (request()->routeIs($routeName) || request()->routeIs($routeName.'.*')) {
                return true;
            }
        }

        return ($item['key'] ?? null) === $currentSection || $routeName === $currentSection;
    }

    /**
     * Filtert und bereitet Navigation-Items fuer die Anzeige vor.
     *
     * Container: sichtbar wenn >= 1 Sub-Item sichtbar; aktiv (= expandiert)
     * wenn >= 1 Sub-Item aktiv ist.
     *
     * @param  array<int|string, mixed>|null  $items
     * @return array<int, array<string, mixed>>
     */
    public function prepareItems(?array $items, string $currentSection = ''): array
    {
        $rawItems = $items ?? $this->getDefaultItems();
        $normalizedItems = $this->normalizeItems($rawItems);

        return collect($normalizedItems)
            ->map(fn (array $item) => $this->prepareItem($item, $currentSection))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Bereitet ein einzelnes Item (Container oder Leaf) auf.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function prepareItem(array $item, string $currentSection): ?array
    {
        if (isset($item['children']) && is_array($item['children'])) {
            $children = collect($item['children'])
                ->map(fn (array $child) => $this->prepareItem($child, $currentSection))
                ->filter()
                ->values()
                ->all();

            if ($children === []) {
                return null;
            }

            $isActive = (bool) collect($children)->contains(fn (array $child) => ! empty($child['active']));

            return [
                'key' => $item['key'],
                'label' => $item['label'],
                'children' => $children,
                'active' => $isActive,
            ];
        }

        if (! $this->hasPermission($item['permissions'] ?? [])) {
            return null;
        }

        $href = $this->buildUrl($item);
        if (empty($href)) {
            return null;
        }

        return [
            'key' => $item['key'],
            'label' => $item['label'],
            'href' => $href,
            'active' => $this->isActive($item, $currentSection),
        ];
    }
}
