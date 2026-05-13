<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-4 — Report table that records the outcome of the
 * `fulfillment_freight_profiles` catalog-FK data migration (one row per
 * profile, unique on `profile_id` for idempotent re-runs).
 *
 * Engineering-Handbuch §24 (idempotent), §27 (per-record traceability).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhl_freight_profile_migration_report', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('profile_id');
            $table->string('original_value', 64)->nullable();
            $table->string('normalized_value', 64)->nullable();
            // Enum: matched | unmatched | already_null
            $table->string('migration_status', 16);
            $table->string('matched_code', 8)->nullable();
            $table->timestamp('migrated_at')->useCurrent();

            // Idempotency: a profile may appear at most once in the report.
            $table->unique('profile_id', 'uq_ffp_migration_report_profile');
            $table->index('migration_status', 'idx_ffp_migration_report_status');

            $table->foreign('profile_id', 'fk_ffp_migration_report_profile')
                ->references('shipping_profile_id')
                ->on('fulfillment_freight_profiles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('dhl_freight_profile_migration_report') && DB::getDriverName() !== 'sqlite') {
            Schema::table('dhl_freight_profile_migration_report', function (Blueprint $table): void {
                $table->dropForeign('fk_ffp_migration_report_profile');
            });
        }

        Schema::dropIfExists('dhl_freight_profile_migration_report');
    }
};
