<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `dhl_products` table — root aggregate of the DHL catalog.
 *
 * Engineering-Handbuch §3–§8: Pure schema operation, no business logic.
 * §24: Idempotent forward/rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhl_products', function (Blueprint $table): void {
            $table->string('code', 8)->primary();
            $table->string('name', 200);
            $table->text('description')->default('');
            // Enum: B2B | B2C | BOTH (see DhlMarketAvailability)
            $table->string('market_availability', 8);
            // JSON arrays of ISO-3166-1 alpha-2 country codes
            $table->json('from_countries');
            $table->json('to_countries');
            // JSON array of DhlPackageType values
            $table->json('allowed_package_types');
            $table->decimal('weight_min_kg', 8, 3);
            $table->decimal('weight_max_kg', 8, 3);
            $table->decimal('dim_max_l_cm', 8, 2);
            $table->decimal('dim_max_b_cm', 8, 2);
            $table->decimal('dim_max_h_cm', 8, 2);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            // Self-referencing FK — added below to allow self-reference
            $table->string('replaced_by_code', 8)->nullable();
            // Enum: seed | api | manual (see DhlCatalogSource)
            $table->string('source', 8);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('deprecated_at', 'idx_dhl_products_deprecated_at');
            $table->index(['valid_from', 'valid_until'], 'idx_dhl_products_valid_window');

            $table->foreign('replaced_by_code', 'fk_dhl_products_replaced_by')
                ->references('code')->on('dhl_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop the self-referencing FK only on drivers that support
        // dropping foreign keys by name (SQLite drops them with the table).
        if (Schema::hasTable('dhl_products') && DB::getDriverName() !== 'sqlite') {
            Schema::table('dhl_products', function (Blueprint $table): void {
                $table->dropForeign('fk_dhl_products_replaced_by');
            });
        }
        Schema::dropIfExists('dhl_products');
    }
};
