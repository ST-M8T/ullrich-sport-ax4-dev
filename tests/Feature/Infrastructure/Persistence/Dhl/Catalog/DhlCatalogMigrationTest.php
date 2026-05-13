<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence\Dhl\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema-introspection tests for the DHL catalog migrations.
 *
 * Engineering-Handbuch §24 (Idempotenz): migrate / rollback / fresh must all
 * succeed. §10–§13 (Datenzugriff / Persistenz-Regel): the persistence schema
 * is the documented contract, not a free-form artefact.
 */
final class DhlCatalogMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'dhl_products',
        'dhl_additional_services',
        'dhl_product_service_assignments',
        'dhl_catalog_audit_log',
        'dhl_catalog_sync_status',
    ];

    public function test_all_catalog_tables_exist(): void
    {
        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), "Table {$table} must exist after migrate.");
        }
    }

    public function test_dhl_products_has_expected_columns(): void
    {
        $required = [
            'code', 'name', 'description', 'market_availability',
            'from_countries', 'to_countries', 'allowed_package_types',
            'weight_min_kg', 'weight_max_kg',
            'dim_max_l_cm', 'dim_max_b_cm', 'dim_max_h_cm',
            'valid_from', 'valid_until', 'deprecated_at',
            'replaced_by_code', 'source', 'synced_at',
            'created_at', 'updated_at',
        ];
        foreach ($required as $column) {
            self::assertTrue(
                Schema::hasColumn('dhl_products', $column),
                "dhl_products must have column {$column}",
            );
        }
    }

    public function test_dhl_additional_services_has_expected_columns(): void
    {
        foreach (['code', 'name', 'category', 'parameter_schema', 'deprecated_at', 'source', 'synced_at'] as $column) {
            self::assertTrue(
                Schema::hasColumn('dhl_additional_services', $column),
                "dhl_additional_services must have column {$column}",
            );
        }
    }

    public function test_assignment_table_has_routing_and_fk_columns(): void
    {
        foreach (
            [
                'product_code', 'service_code', 'from_country', 'to_country',
                'payer_code', 'requirement', 'default_parameters', 'source', 'synced_at',
            ] as $column
        ) {
            self::assertTrue(
                Schema::hasColumn('dhl_product_service_assignments', $column),
                "dhl_product_service_assignments must have column {$column}",
            );
        }
    }

    public function test_audit_log_has_expected_columns(): void
    {
        foreach (['entity_type', 'entity_key', 'action', 'actor', 'diff', 'created_at'] as $column) {
            self::assertTrue(
                Schema::hasColumn('dhl_catalog_audit_log', $column),
                "dhl_catalog_audit_log must have column {$column}",
            );
        }
    }

    public function test_sync_status_has_expected_columns(): void
    {
        foreach (
            [
                'id', 'last_attempt_at', 'last_success_at', 'last_error',
                'consecutive_failures', 'mail_sent_for_failure_streak',
            ] as $column
        ) {
            self::assertTrue(
                Schema::hasColumn('dhl_catalog_sync_status', $column),
                "dhl_catalog_sync_status must have column {$column}",
            );
        }
    }

    public function test_self_replacement_constraint_blocks_insert(): void
    {
        // Domain-level guard is authoritative; the DB-level CHECK / trigger
        // is a safety net. Verify that direct SQL with replaced_by_code = code
        // is rejected on the supported drivers.
        $driver = \DB::getDriverName();
        if (! in_array($driver, ['pgsql', 'mysql', 'mariadb', 'sqlite'], true)) {
            $this->markTestSkipped('Driver does not support the self-replacement guard.');
        }

        $this->expectException(\Throwable::class);
        \DB::table('dhl_products')->insert([
            'code' => 'SELF',
            'name' => 'Self replacement',
            'description' => '',
            'market_availability' => 'B2B',
            'from_countries' => json_encode(['DE']),
            'to_countries' => json_encode(['AT']),
            'allowed_package_types' => json_encode(['PLT']),
            'weight_min_kg' => 0,
            'weight_max_kg' => 1000,
            'dim_max_l_cm' => 240,
            'dim_max_b_cm' => 120,
            'dim_max_h_cm' => 180,
            'valid_from' => '2024-01-01 00:00:00',
            'replaced_by_code' => 'SELF',
            'source' => 'seed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_migrate_rollback_migrate_cycle_is_idempotent(): void
    {
        // The RefreshDatabase trait already executed `migrate:fresh`. Confirm
        // rollback and re-migrate succeed without errors and leave the schema
        // intact.
        $rollback = Artisan::call('migrate:rollback', ['--step' => 6]);
        self::assertSame(0, $rollback, 'migrate:rollback must exit cleanly.');

        $migrate = Artisan::call('migrate');
        self::assertSame(0, $migrate, 'migrate must exit cleanly after rollback.');

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), "Table {$table} must exist after re-migrate.");
        }
    }
}
