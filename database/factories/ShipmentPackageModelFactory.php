<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentPackageModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentPackageModel>
 */
final class ShipmentPackageModelFactory extends Factory
{
    protected $model = ShipmentPackageModel::class;

    public function definition(): array
    {
        return [
            'shipment_order_id' => ShipmentOrderModel::factory(),
            'packaging_profile_id' => FulfillmentPackagingProfileModel::factory(),
            'package_reference' => strtoupper($this->faker->bothify('PKG-#####')),
            'quantity' => $this->faker->numberBetween(1, 4),
            'weight_kg' => $this->faker->randomFloat(2, 0.5, 40),
            'length_mm' => $this->faker->numberBetween(120, 1200),
            'width_mm' => $this->faker->numberBetween(80, 800),
            'height_mm' => $this->faker->numberBetween(80, 600),
            'truck_slot_units' => $this->faker->numberBetween(1, 4),
            'metadata' => [
                'stackable' => $this->faker->boolean(),
            ],
        ];
    }
}
