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
        Schema::create('shipment_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_order_id')->unique();
            $table->unsignedBigInteger('customer_number')->nullable();
            $table->unsignedBigInteger('plenty_order_id')->nullable();
            $table->string('order_type', 64)->nullable();
            $table->foreignId('sender_profile_id')
                ->nullable()
                ->constrained('fulfillment_sender_profiles')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('sender_code', 64)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->string('destination_country', 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->boolean('is_booked')->default(false);
            $table->timestamp('booked_at')->nullable();
            $table->string('booked_by')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->string('last_export_filename')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_order_id')
                ->constrained('shipment_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->string('sku', 64)->nullable();
            $table->string('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->foreignId('packaging_profile_id')
                ->nullable()
                ->constrained('fulfillment_packaging_profiles')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->boolean('is_assembly')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_order_id')
                ->constrained('shipment_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('packaging_profile_id')
                ->nullable()
                ->constrained('fulfillment_packaging_profiles')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('package_reference')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->unsignedSmallInteger('truck_slot_units')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('carrier_code', 64);
            $table->unsignedInteger('shipping_profile_id')->nullable();
            $table->string('tracking_number', 191)->unique();
            $table->string('status_code', 64)->nullable();
            $table->string('status_description')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_sync_after')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('volume_dm3', 10, 3)->nullable();
            $table->unsignedInteger('pieces_count')->nullable();
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->json('last_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('shipping_profile_id')
                ->references('shipping_profile_id')
                ->on('fulfillment_freight_profiles')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });

        Schema::create('shipment_order_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_order_id')
                ->constrained('shipment_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['shipment_order_id', 'shipment_id'], 'shipment_order_shipments_unique');
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('event_code', 64)->nullable();
            $table->string('event_status', 64)->nullable();
            $table->string('event_description')->nullable();
            $table->string('facility')->nullable();
            $table->string('city')->nullable();
            $table->string('country_iso2', 2)->nullable();
            $table->timestamp('event_occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shipment_id', 'event_occurred_at'], 'shipment_events_event_idx');
        });

        Schema::create('dispatch_lists', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('closed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('close_requested_at')->nullable();
            $table->string('close_requested_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->string('export_filename')->nullable();
            $table->unsignedInteger('total_packages')->nullable();
            $table->unsignedInteger('total_orders')->nullable();
            $table->unsignedInteger('total_truck_slots')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('dispatch_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_list_id')
                ->constrained('dispatch_lists')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('barcode');
            $table->foreignId('shipment_order_id')
                ->nullable()
                ->constrained('shipment_orders')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('captured_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('captured_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['dispatch_list_id', 'barcode'], 'dispatch_scans_unique_barcode');
        });

        Schema::create('dispatch_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_list_id')
                ->constrained('dispatch_lists')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('total_packages')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('total_truck_slots')->default(0);
            $table->json('metrics')->nullable();
            $table->timestamps();
            $table->unique('dispatch_list_id');
        });

        Schema::create('tracking_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 64);
            $table->string('status', 32)->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('attempt')->default(0);
            $table->text('last_error')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['job_type', 'status', 'scheduled_at']);
        });

        Schema::create('tracking_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')
                ->nullable()
                ->constrained('shipments')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('alert_type', 64);
            $table->string('severity', 16)->default('info');
            $table->string('channel', 32)->nullable();
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['alert_type', 'severity', 'sent_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 32)->default('user');
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_name')->nullable();
            $table->string('action');
            $table->json('context')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['actor_type', 'actor_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('system_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->string('job_type', 64)->nullable();
            $table->string('run_context')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['job_name', 'status']);
        });

        Schema::create('domain_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_name');
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['event_name', 'occurred_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('setting_key')->primary();
            $table->text('setting_value')->nullable();
            $table->string('value_type', 32)->default('string');
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->unique();
            $table->string('description')->nullable();
            $table->string('subject');
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('notifications_queue', function (Blueprint $table) {
            $table->id();
            $table->string('notification_type');
            $table->string('channel', 32)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_queue');
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('domain_events');
        Schema::dropIfExists('system_jobs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('tracking_alerts');
        Schema::dropIfExists('tracking_jobs');
        Schema::dropIfExists('dispatch_metrics');
        Schema::dropIfExists('dispatch_scans');
        Schema::dropIfExists('dispatch_lists');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_order_shipments');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('shipment_packages');
        Schema::dropIfExists('shipment_order_items');
        Schema::dropIfExists('shipment_orders');
    }
};
