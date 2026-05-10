<?php

declare(strict_types=1);

namespace Tests\Feature\Layout;

use App\Support\UI\NavigationService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Verifiziert das Fail-Closed-Verhalten des NavigationService.
 *
 * Frueher fiel `hasPermission([])` auf `Gate::allows('admin.access')`
 * zurueck, was systemweit jedem authentifizierten Backend-Nutzer ein
 * permissionsloses Item gezeigt haette.
 *
 * Engineering-Handbuch Section 19/67: Fail-Closed Default,
 * Fail-Fast bei Konfigurationsfehlern.
 */
final class NavigationServiceFailClosedTest extends TestCase
{
    public function test_has_permission_returns_false_for_empty_permission_list(): void
    {
        /** @var NavigationService $service */
        $service = $this->app->make(NavigationService::class);

        self::assertFalse($service->hasPermission([]));
    }

    public function test_normalize_items_rejects_array_item_without_permissions(): void
    {
        /** @var NavigationService $service */
        $service = $this->app->make(NavigationService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->normalizeItems([
            'orphan' => [
                'label' => 'Item ohne Permission',
                'route' => 'orphan',
            ],
        ]);
    }

    public function test_normalize_items_rejects_scalar_items(): void
    {
        /** @var NavigationService $service */
        $service = $this->app->make(NavigationService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->normalizeItems(['Skalar-Item ohne Struktur']);
    }

    public function test_default_items_pass_normalization_with_permissions_set(): void
    {
        /** @var NavigationService $service */
        $service = $this->app->make(NavigationService::class);

        $items = $service->normalizeItems($service->getDefaultItems());

        self::assertNotEmpty($items);

        // Phase A (t22): Top-Level-Items sind Container ohne eigene Permission;
        // jede Permission-Pflicht greift auf der Sub-Item-Ebene.
        foreach ($items as $group) {
            self::assertArrayHasKey(
                'children',
                $group,
                sprintf('Default-Gruppe "%s" muss Children enthalten.', $group['key'] ?? '?'),
            );
            self::assertNotEmpty(
                $group['children'],
                sprintf('Default-Gruppe "%s" darf nicht leer sein.', $group['key'] ?? '?'),
            );

            foreach ($group['children'] as $child) {
                self::assertNotEmpty(
                    $child['permissions'] ?? [],
                    sprintf(
                        'Default-Sub-Item "%s" muss mindestens eine Permission deklarieren.',
                        $child['key'] ?? '?',
                    ),
                );
            }
        }
    }
}
