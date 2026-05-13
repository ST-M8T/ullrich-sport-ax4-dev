<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `dhl_catalog_sync_status` table — single-row state tracker
 * for the DHL catalog sync (PROJ-2).
 *
 * `id` is a varchar PK so the application can use a fixed sentinel
 * ('current') and rely on upsert semantics. `mail_sent_for_failure_streak`
 * is the idempotency flag that prevents the failure-streak notification
 * from being sent more than once per failure series.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhl_catalog_sync_status', function (Blueprint $table): void {
            $table->string('id', 16)->primary();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('consecutive_failures')->default(0);
            $table->boolean('mail_sent_for_failure_streak')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhl_catalog_sync_status');
    }
};
