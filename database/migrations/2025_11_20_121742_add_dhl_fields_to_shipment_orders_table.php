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
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->string('dhl_shipment_id')->nullable()->after('last_export_filename');
            $table->text('dhl_label_url')->nullable()->after('dhl_shipment_id');
            $table->longText('dhl_label_pdf_base64')->nullable()->after('dhl_label_url');
            $table->string('dhl_pickup_reference')->nullable()->after('dhl_label_pdf_base64');
            $table->string('dhl_product_id')->nullable()->after('dhl_pickup_reference');
            $table->json('dhl_booking_payload')->nullable()->after('dhl_product_id');
            $table->json('dhl_booking_response')->nullable()->after('dhl_booking_payload');
            $table->text('dhl_booking_error')->nullable()->after('dhl_booking_response');
            $table->timestamp('dhl_booked_at')->nullable()->after('dhl_booking_error');

            $table->index('dhl_shipment_id', 'shipment_orders_dhl_shipment_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->dropIndex('shipment_orders_dhl_shipment_id_idx');
            $table->dropColumn([
                'dhl_shipment_id',
                'dhl_label_url',
                'dhl_label_pdf_base64',
                'dhl_pickup_reference',
                'dhl_product_id',
                'dhl_booking_payload',
                'dhl_booking_response',
                'dhl_booking_error',
                'dhl_booked_at',
            ]);
        });
    }
};
