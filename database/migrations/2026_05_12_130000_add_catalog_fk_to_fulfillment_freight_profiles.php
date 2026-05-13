<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-4 Migration A (additive, reversible).
 *
 * Adds the new catalog-FK column `dhl_product_code` (references `dhl_products.code`)
 * and the typed JSON column `dhl_default_service_parameters` to
 * `fulfillment_freight_profiles`.
 *
 * The legacy columns (`dhl_product_id`, `dhl_default_service_codes`) remain in
 * place — they are dropped by a later cleanup migration (out of scope here).
 *
 * Engineering-Handbuch §3–§8 (pure schema), §10 (no domain coupling),
 * §24 (idempotent forward/rollback).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_freight_profiles', function (Blueprint $table): void {
            $table->string('dhl_product_code', 8)
                ->nullable()
                ->after('dhl_product_id');

            $table->json('dhl_default_service_parameters')
                ->nullable()
                ->after('dhl_default_service_codes');

            $table->index('dhl_product_code', 'idx_ffp_dhl_product_code');
        });

        // Foreign key — SQLite can declare them only at table-create time when
        // using a fresh migration; with foreign_key_constraints on, ALTER TABLE
        // ADD CONSTRAINT is not supported. Skip the named FK on sqlite — the
        // referential integrity is enforced at the application layer in tests
        // and by Postgres/MySQL in real deployments.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('fulfillment_freight_profiles', function (Blueprint $table): void {
                $table->foreign('dhl_product_code', 'fk_ffp_dhl_product_code')
                    ->references('code')->on('dhl_products')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fulfillment_freight_profiles') && DB::getDriverName() !== 'sqlite') {
            Schema::table('fulfillment_freight_profiles', function (Blueprint $table): void {
                $table->dropForeign('fk_ffp_dhl_product_code');
            });
        }

        Schema::table('fulfillment_freight_profiles', function (Blueprint $table): void {
            $table->dropIndex('idx_ffp_dhl_product_code');
            $table->dropColumn(['dhl_product_code', 'dhl_default_service_parameters']);
        });
    }
};
