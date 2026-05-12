<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the FK link from a ShipmentOrder to its FulfillmentFreightProfile.
 *
 * Background (Goal "DHL Freight Integration produktionsreif machen", t30):
 *   The DhlSettingsResolver implements an Account-Number-Override per
 *   FulfillmentFreightProfile (Profile.account_number > Default System-Setting),
 *   but the ShipmentOrder aggregate had no field to carry the freight profile.
 *   That made the per-profile override unreachable from booking. This migration
 *   closes the design gap.
 *
 * The column is nullable to keep legacy rows valid; the DhlSettingsResolver
 * already falls back to the system default when null is supplied (Fail-Fast §67
 * applies only when BOTH sources are absent).
 *
 * The FK references {@see fulfillment_freight_profiles.shipping_profile_id}
 * (the table's actual primary key — see 2024_11_25_000001 create migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shipment_orders', 'freight_profile_id')) {
            return;
        }

        Schema::table('shipment_orders', function (Blueprint $table): void {
            $table->unsignedInteger('freight_profile_id')->nullable()->after('sender_code');

            $table->foreign('freight_profile_id', 'shipment_orders_freight_profile_id_fk')
                ->references('shipping_profile_id')
                ->on('fulfillment_freight_profiles')
                ->nullOnDelete();

            $table->index('freight_profile_id', 'shipment_orders_freight_profile_id_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shipment_orders', 'freight_profile_id')) {
            return;
        }

        Schema::table('shipment_orders', function (Blueprint $table): void {
            $table->dropForeign('shipment_orders_freight_profile_id_fk');
            $table->dropIndex('shipment_orders_freight_profile_id_idx');
            $table->dropColumn('freight_profile_id');
        });
    }
};
