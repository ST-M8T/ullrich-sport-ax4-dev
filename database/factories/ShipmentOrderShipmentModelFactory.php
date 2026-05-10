<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderShipmentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentOrderShipmentModel>
 */
final class ShipmentOrderShipmentModelFactory extends Factory
{
    protected $model = ShipmentOrderShipmentModel::class;

    public function definition(): array
    {
        return [
            'shipment_order_id' => ShipmentOrderModel::factory(),
            'shipment_id' => ShipmentModel::factory(),
        ];
    }
}
