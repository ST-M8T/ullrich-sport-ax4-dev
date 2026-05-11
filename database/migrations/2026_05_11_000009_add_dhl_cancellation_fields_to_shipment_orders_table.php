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
            $table->timestamp('dhl_cancelled_at')->nullable()->after('dhl_booked_at');
            $table->string('dhl_cancelled_by', 100)->nullable()->after('dhl_cancelled_at');
            $table->string('dhl_cancellation_reason', 500)->nullable()->after('dhl_cancelled_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->dropColumn([
                'dhl_cancelled_at',
                'dhl_cancelled_by',
                'dhl_cancellation_reason',
            ]);
        });
    }
};