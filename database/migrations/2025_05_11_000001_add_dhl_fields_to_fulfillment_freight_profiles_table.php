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
        Schema::table('fulfillment_freight_profiles', function (Blueprint $table): void {
            $table->string('dhl_product_id', 32)->nullable()->after('label');
            $table->json('dhl_default_service_codes')->nullable()->after('dhl_product_id');
            $table->json('shipping_method_mapping')->nullable()->after('dhl_default_service_codes');
            $table->string('account_number', 32)->nullable()->after('shipping_method_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fulfillment_freight_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'dhl_product_id',
                'dhl_default_service_codes',
                'shipping_method_mapping',
                'account_number',
            ]);
        });
    }
};