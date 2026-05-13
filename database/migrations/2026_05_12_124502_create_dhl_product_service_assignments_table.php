<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `dhl_product_service_assignments` table (N:M product↔service
 * with routing context: from_country, to_country, payer_code; NULL = global).
 *
 * Uniqueness across NULL columns is enforced via a PostgreSQL functional
 * unique index on COALESCE(...) so that a `(product, service, NULL, NULL, NULL)`
 * row cannot be duplicated. On other drivers (sqlite/mysql) the regular
 * UNIQUE constraint applies (with engine-specific NULL semantics).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhl_product_service_assignments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('product_code', 8);
            $table->string('service_code', 8);
            $table->string('from_country', 2)->nullable();
            $table->string('to_country', 2)->nullable();
            $table->string('payer_code', 8)->nullable();
            // Enum: allowed | required | forbidden (see DhlServiceRequirement)
            $table->string('requirement', 16);
            $table->json('default_parameters')->nullable();
            // Enum: seed | api | manual
            $table->string('source', 8);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('product_code', 'fk_dpsa_product_code')
                ->references('code')->on('dhl_products')
                ->cascadeOnDelete();

            $table->foreign('service_code', 'fk_dpsa_service_code')
                ->references('code')->on('dhl_additional_services')
                ->cascadeOnDelete();

            // Lookup index for findAllowedServicesFor()
            $table->index(
                ['product_code', 'from_country', 'to_country', 'payer_code'],
                'idx_dpsa_lookup'
            );
            $table->index('service_code', 'idx_dpsa_service');

            // Conventional unique (NULLs are distinct in PG; functional index below
            // closes that gap deterministically).
            $table->unique(
                ['product_code', 'service_code', 'from_country', 'to_country', 'payer_code'],
                'uq_dpsa_routing'
            );
        });

        // Postgres-specific: functional unique index treating NULL as '*'
        // so duplicates with NULL routing dimensions are reliably prevented.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX uq_dpsa_routing_coalesced
                    ON dhl_product_service_assignments (
                        product_code,
                        service_code,
                        COALESCE(from_country, '*'),
                        COALESCE(to_country, '*'),
                        COALESCE(payer_code, '*')
                    )
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS uq_dpsa_routing_coalesced');
        }

        // SQLite drops foreign keys together with the table and does not
        // support dropping them by name — only run the explicit dropForeign
        // calls on drivers that support it.
        if (Schema::hasTable('dhl_product_service_assignments') && DB::getDriverName() !== 'sqlite') {
            Schema::table('dhl_product_service_assignments', function (Blueprint $table): void {
                $table->dropForeign('fk_dpsa_product_code');
                $table->dropForeign('fk_dpsa_service_code');
            });
        }

        Schema::dropIfExists('dhl_product_service_assignments');
    }
};
