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
        Schema::create('fulfillment_packaging_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('package_name');
            $table->string('packaging_code')->nullable()->unique();
            $table->unsignedInteger('length_mm');
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->unsignedTinyInteger('truck_slot_units')->default(1);
            $table->unsignedSmallInteger('max_units_per_pallet_same_recipient')->default(1);
            $table->unsignedSmallInteger('max_units_per_pallet_mixed_recipient')->default(1);
            $table->unsignedSmallInteger('max_stackable_pallets_same_recipient')->default(1);
            $table->unsignedSmallInteger('max_stackable_pallets_mixed_recipient')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('fulfillment_sender_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('sender_code')->unique();
            $table->string('display_name');
            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('street_name');
            $table->string('street_number', 32)->nullable();
            $table->string('address_addition')->nullable();
            $table->string('postal_code', 32);
            $table->string('city');
            $table->string('country_iso2', 2);
            $table->timestamps();
        });

        Schema::create('fulfillment_sender_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('priority')->default(100);
            $table->string('rule_type', 64);
            $table->string('match_value');
            $table->foreignId('target_sender_id')
                ->constrained('fulfillment_sender_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['priority', 'is_active']);
        });

        Schema::create('fulfillment_assembly_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assembly_item_id')->unique();
            $table->foreignId('assembly_packaging_id')
                ->constrained('fulfillment_packaging_profiles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('assembly_weight_kg', 8, 2)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('fulfillment_variation_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->string('variation_name')->nullable();
            $table->string('default_state', 16);
            $table->foreignId('default_packaging_id')
                ->constrained('fulfillment_packaging_profiles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('default_weight_kg', 8, 2)->nullable();
            $table->foreignId('assembly_option_id')
                ->nullable()
                ->constrained('fulfillment_assembly_options')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['item_id', 'variation_id'], 'fulfillment_variation_unique');
        });

        Schema::create('fulfillment_freight_profiles', function (Blueprint $table) {
            $table->unsignedInteger('shipping_profile_id')->primary();
            $table->string('label')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_freight_profiles');
        Schema::dropIfExists('fulfillment_variation_profiles');
        Schema::dropIfExists('fulfillment_assembly_options');
        Schema::dropIfExists('fulfillment_sender_rules');
        Schema::dropIfExists('fulfillment_sender_profiles');
        Schema::dropIfExists('fulfillment_packaging_profiles');
    }
};
