<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reporting_shipment_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('event_name');
            $table->string('aggregate_id');
            $table->string('tracking_number')->nullable();
            $table->string('event_code', 64)->nullable();
            $table->string('status', 64)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('carrier_code', 64)->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tracking_number', 'occurred_at'], 'shipment_events_tracking_idx');
            $table->index(['status', 'occurred_at'], 'shipment_events_status_idx');
        });

        Schema::create('reporting_dispatch_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('event_name');
            $table->string('aggregate_id');
            $table->string('barcode')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['aggregate_id', 'occurred_at'], 'dispatch_events_aggregate_idx');
            $table->index(['event_name', 'occurred_at'], 'dispatch_events_name_idx');
        });

        Schema::create('reporting_order_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('event_name');
            $table->string('aggregate_id');
            $table->string('external_order_id')->nullable();
            $table->string('status', 64)->nullable();
            $table->boolean('is_update')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['external_order_id', 'synced_at'], 'order_events_external_idx');
            $table->index(['status', 'synced_at'], 'order_events_status_idx');
        });

        Schema::create('reporting_notification_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('event_name');
            $table->string('aggregate_id');
            $table->string('channel', 32)->nullable();
            $table->string('notification_type')->nullable();
            $table->string('recipient')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('template')->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['channel', 'sent_at'], 'notification_events_channel_idx');
            $table->index(['notification_type', 'sent_at'], 'notification_events_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_notification_events');
        Schema::dropIfExists('reporting_order_events');
        Schema::dropIfExists('reporting_dispatch_events');
        Schema::dropIfExists('reporting_shipment_events');
    }
};
