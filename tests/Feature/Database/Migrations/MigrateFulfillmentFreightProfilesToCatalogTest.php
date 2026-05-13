<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for PROJ-4 Migration B
 * ({@see 2026_05_12_130002_migrate_fulfillment_freight_profiles_to_catalog.php}).
 *
 * The data migration runs as part of {@see RefreshDatabase} when the catalog
 * is empty (early-return path), so each test seeds catalog + profiles
 * manually and re-invokes the migration directly to exercise the mapping
 * logic, the report writer and the idempotency guards.
 *
 * Engineering-Handbuch §24 (Idempotenz), §27 (Import-Robustness),
 * §15 (only structural validation in the migration).
 */
final class MigrateFulfillmentFreightProfilesToCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require database_path(
            'migrations/2026_05_12_130002_migrate_fulfillment_freight_profiles_to_catalog.php'
        );
    }

    public function test_legacy_columns_still_exist_after_migration_a(): void
    {
        self::assertTrue(Schema::hasColumn('fulfillment_freight_profiles', 'dhl_product_id'));
        self::assertTrue(Schema::hasColumn('fulfillment_freight_profiles', 'dhl_default_service_codes'));
        self::assertTrue(Schema::hasColumn('fulfillment_freight_profiles', 'dhl_product_code'));
        self::assertTrue(Schema::hasColumn('fulfillment_freight_profiles', 'dhl_default_service_parameters'));
    }

    public function test_migrates_matched_unmatched_null_and_deprecated_profiles(): void
    {
        $this->seedCatalog(['ECI', 'EUC', 'EPC']);

        // (a) exact match
        $this->insertProfile(101, 'ECI', ['NOT', 'PIN']);
        // (b) empty string → already_null
        $this->insertProfile(102, '', null);
        // (c) NULL → already_null
        $this->insertProfile(103, null, null);
        // (d) typo → unmatched
        $this->insertProfile(104, 'ECII', ['NOT']);
        // (e) lowercase + whitespace → matched after normalisation
        $this->insertProfile(105, '  euc ', ['SAT']);

        $this->migration->up();

        $rows = DB::table('fulfillment_freight_profiles')
            ->orderBy('shipping_profile_id')->get()->keyBy('shipping_profile_id');

        self::assertSame('ECI', $rows[101]->dhl_product_code);
        self::assertSame('EUC', $rows[105]->dhl_product_code);
        self::assertNull($rows[102]->dhl_product_code);
        self::assertNull($rows[103]->dhl_product_code);
        self::assertNull($rows[104]->dhl_product_code);

        // Service-codes transformed to typed shape for matched + unmatched rows
        self::assertSame(
            [['code' => 'NOT', 'parameters' => null], ['code' => 'PIN', 'parameters' => null]],
            json_decode((string) $rows[101]->dhl_default_service_parameters, true),
        );
        self::assertSame(
            [['code' => 'SAT', 'parameters' => null]],
            json_decode((string) $rows[105]->dhl_default_service_parameters, true),
        );
        self::assertNull($rows[102]->dhl_default_service_parameters);

        $report = DB::table('dhl_freight_profile_migration_report')
            ->orderBy('profile_id')->get()->keyBy('profile_id');

        self::assertCount(5, $report);
        self::assertSame('matched', $report[101]->migration_status);
        self::assertSame('ECI', $report[101]->matched_code);
        self::assertSame('already_null', $report[102]->migration_status);
        self::assertSame('already_null', $report[103]->migration_status);
        self::assertSame('unmatched', $report[104]->migration_status);
        self::assertSame('ECII', $report[104]->normalized_value);
        self::assertSame('matched', $report[105]->migration_status);
        self::assertSame('EUC', $report[105]->matched_code);
    }

    public function test_rerunning_the_migration_is_idempotent(): void
    {
        $this->seedCatalog(['ECI', 'EUC']);
        $this->insertProfile(201, 'ECI', ['NOT']);
        $this->insertProfile(202, 'BROKEN', null);

        $this->migration->up();
        $firstReport = DB::table('dhl_freight_profile_migration_report')->orderBy('profile_id')->get();

        // Second invocation — must converge to identical end-state, no duplicate
        // report rows (unique key on profile_id), no schema drift.
        $this->migration->up();

        $secondReport = DB::table('dhl_freight_profile_migration_report')->orderBy('profile_id')->get();

        self::assertCount(2, $secondReport);
        self::assertEquals(
            $firstReport->map(fn ($r) => $r->profile_id)->all(),
            $secondReport->map(fn ($r) => $r->profile_id)->all(),
        );

        $profile = DB::table('fulfillment_freight_profiles')
            ->where('shipping_profile_id', 201)->first();
        self::assertSame('ECI', $profile->dhl_product_code);
    }

    public function test_migration_matches_profiles_referencing_deprecated_products(): void
    {
        $this->seedCatalog(['ECI'], deprecated: ['ECI']);
        $this->insertProfile(301, 'ECI', null);

        $this->migration->up();

        $profile = DB::table('fulfillment_freight_profiles')
            ->where('shipping_profile_id', 301)->first();
        self::assertSame('ECI', $profile->dhl_product_code);

        $report = DB::table('dhl_freight_profile_migration_report')
            ->where('profile_id', 301)->first();
        self::assertSame('matched', $report->migration_status);
    }

    public function test_aborts_when_catalog_is_empty_but_profiles_reference_codes(): void
    {
        DB::table('dhl_products')->delete();
        $this->insertProfile(401, 'ECI', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/dhl_products.+empty/i');

        $this->migration->up();
    }

    public function test_no_op_when_catalog_and_profiles_are_both_empty(): void
    {
        DB::table('dhl_products')->delete();

        // Must not throw — fresh installs without any data are valid.
        $this->migration->up();

        self::assertSame(0, DB::table('dhl_freight_profile_migration_report')->count());
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $deprecated
     */
    private function seedCatalog(array $codes, array $deprecated = []): void
    {
        $now = now();
        foreach ($codes as $code) {
            DB::table('dhl_products')->insert([
                'code' => $code,
                'name' => 'Test ' . $code,
                'description' => '',
                'market_availability' => 'B2B',
                'from_countries' => json_encode(['DE']),
                'to_countries' => json_encode(['AT']),
                'allowed_package_types' => json_encode(['PLT']),
                'weight_min_kg' => 0,
                'weight_max_kg' => 2500,
                'dim_max_l_cm' => 240,
                'dim_max_b_cm' => 120,
                'dim_max_h_cm' => 220,
                'valid_from' => $now,
                'valid_until' => null,
                'deprecated_at' => in_array($code, $deprecated, true) ? $now : null,
                'replaced_by_code' => null,
                'source' => 'seed',
                'synced_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  list<string>|null  $serviceCodes
     */
    private function insertProfile(int $profileId, ?string $productId, ?array $serviceCodes): void
    {
        DB::table('fulfillment_freight_profiles')->insert([
            'shipping_profile_id' => $profileId,
            'label' => 'Profile ' . $profileId,
            'dhl_product_id' => $productId,
            'dhl_default_service_codes' => $serviceCodes !== null ? json_encode($serviceCodes) : null,
            'created_at' => now(),
        ]);
    }
}
