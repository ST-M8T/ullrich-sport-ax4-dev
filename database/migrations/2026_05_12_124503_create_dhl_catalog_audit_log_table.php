<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `dhl_catalog_audit_log` table — append-only audit trail.
 *
 * Audit entries are immutable: no `updated_at` column on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhl_catalog_audit_log', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Enum: product | service | assignment
            $table->string('entity_type', 16);
            // Composite key string (e.g. product code, service code, or
            // "<product>:<service>:<from>:<to>:<payer>" for assignments).
            $table->string('entity_key', 128);
            // Enum: created | updated | deprecated | restored
            $table->string('action', 16);
            // e.g. "system:dhl-sync" or "user:42"
            $table->string('actor', 128);
            // {"before": ..., "after": ...}
            $table->json('diff');
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['entity_type', 'entity_key', 'created_at'],
                'idx_dhl_audit_lookup'
            );
            $table->index(['actor', 'created_at'], 'idx_dhl_audit_actor_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhl_catalog_audit_log');
    }
};
