<?php

declare(strict_types=1);

namespace Tests\Unit\Support\UI;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use App\Support\UI\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit-Tests fuer NavigationService Phase A (t22) + Phase B (t31).
 *
 * Verifiziert:
 * - Default-Struktur: 5 Top-Level-Gruppen (t14) mit insgesamt 18 Sub-Items.
 * - Container-Sichtbarkeit als OR ueber Sub-Item-Sichtbarkeiten.
 * - Pro Rolle die erwartete Anzahl sichtbarer Gruppen (t14-Mockup).
 * - Fail-Closed bei Container ohne Children und Sub-Item ohne Permission.
 * - Tracking-Gruppe mit 3 Sub-Items (Wave 14).
 * - IntegrationPolicy gesplittet (configuration.integrations.manage).
 * - t22: 6 groups -> 5 groups (Monitoring renamed to System, Stammdaten+Verwaltung merged).
 */
final class NavigationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NavigationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(NavigationService::class);
    }

    public function test_default_items_have_five_top_level_groups(): void
    {
        $items = $this->service->getDefaultItems();

        self::assertCount(5, $items, 'Wave 7: stammdaten+verwaltung merged to stammdaten-benutzer => 5 groups (operations, stammdaten-benutzer, tracking, system, konfiguration).');
        $keys = array_column($items, 'key');
        self::assertSame(
            ['operations', 'stammdaten-benutzer', 'tracking', 'system', 'konfiguration'],
            $keys,
        );
    }

    public function test_default_items_have_sixteen_sub_items_in_total(): void
    {
        $items = $this->service->getDefaultItems();
        $count = 0;
        foreach ($items as $group) {
            self::assertArrayHasKey('children', $group, "Group '{$group['key']}' muss Children haben.");
            $count += count($group['children']);
        }

        self::assertSame(18, $count, 'Wave 14: insgesamt 18 Sub-Items unter 6 Gruppen (Tracking erweitert um Jobs + Alerts).');
    }

    public function test_every_sub_item_declares_exactly_one_permission(): void
    {
        $items = $this->service->getDefaultItems();
        foreach ($items as $group) {
            foreach ($group['children'] as $sub) {
                self::assertArrayHasKey('permissions', $sub, "Sub-Item '{$sub['key']}' braucht Permissions.");
                self::assertCount(
                    1,
                    $sub['permissions'],
                    "Sub-Item '{$sub['key']}' muss genau eine Permission deklarieren.",
                );
            }
        }
    }

    public function test_container_without_children_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->normalizeItems([
            [
                'key' => 'leer',
                'label' => 'Leer',
                'children' => [],
            ],
        ]);
    }

    public function test_sub_item_without_permission_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->normalizeItems([
            [
                'key' => 'group',
                'label' => 'Group',
                'children' => [
                    ['key' => 'orphan', 'label' => 'Orphan', 'route' => 'orphan'],
                ],
            ],
        ]);
    }

    public function test_container_visibility_or_over_sub_items_admin_sees_operations(): void
    {
        $this->actingAsRole('admin');

        $prepared = $this->service->prepareItems(null);
        $operations = $this->findGroup($prepared, 'operations');

        self::assertNotNull($operations, 'Admin muss Operations-Gruppe sehen.');
        self::assertCount(4, $operations['children']);
    }

    public function test_container_hidden_when_no_sub_item_visible(): void
    {
        $this->actingAsRole('noaccess');

        $prepared = $this->service->prepareItems(null);

        self::assertSame([], $prepared, 'noaccess-Rolle darf 0 Gruppen sehen.');
    }

    public function test_container_visible_when_at_least_one_sub_item_visible(): void
    {
        // viewer hat NUR fulfillment.orders.view aus Operations-Gruppe;
        // Operations muss dennoch sichtbar sein (OR-Semantik).
        $this->actingAsRole('viewer');

        $prepared = $this->service->prepareItems(null);
        $operations = $this->findGroup($prepared, 'operations');

        self::assertNotNull($operations, 'Operations sichtbar wegen Auftraege-Sicht.');
        self::assertCount(1, $operations['children']);
        self::assertSame('fulfillment-orders', $operations['children'][0]['key']);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function expectedGroupCountsPerRole(): array
    {
        // Quelle: t14-Mockup + config/identity.php Stand AX4 Wave 9 + Wave 7 merge (stammdaten+verwaltung -> stammdaten-benutzer).
        // Wave 7: stammdaten + verwaltung merged to "Stammdaten & Benutzer" => 6 groups -> 5 groups.
        // Wave 14: Monitoring renamed to "System". Total: 5 groups (operations, stammdaten-benutzer, tracking, system, konfiguration).
        return [
            'admin' => ['admin', 5],
            'leiter' => ['leiter', 5],
            'operations' => ['operations', 4],
            'support' => ['support', 2],
            'configuration' => ['configuration', 2],
            'identity' => ['identity', 1],
            'viewer' => ['viewer', 3],
            'noaccess' => ['noaccess', 0],
        ];
    }

    #[DataProvider('expectedGroupCountsPerRole')]
    public function test_visible_group_count_per_role(string $role, int $expected): void
    {
        $this->actingAsRole($role);

        $prepared = $this->service->prepareItems(null);

        self::assertCount(
            $expected,
            $prepared,
            "Rolle '{$role}' muss {$expected} Top-Level-Gruppen sehen, sah ".count($prepared).'.',
        );
    }

    private function actingAsRole(string $role): void
    {
        $user = UserModel::query()->create([
            'username' => "test-{$role}",
            'display_name' => "Test {$role}",
            'email' => "{$role}@example.com",
            'password_hash' => bcrypt('password'),
            'role' => $role,
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);
    }

    /**
     * @param  array<int, array<string, mixed>>  $prepared
     * @return array<string, mixed>|null
     */
    private function findGroup(array $prepared, string $key): ?array
    {
        foreach ($prepared as $group) {
            if (($group['key'] ?? null) === $key) {
                return $group;
            }
        }

        return null;
    }
}
