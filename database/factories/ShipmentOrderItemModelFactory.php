<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderItemModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentOrderItemModel>
 */
final class ShipmentOrderItemModelFactory extends Factory
{
    protected $model = ShipmentOrderItemModel::class;

    public function definition(): array
    {
        return [
            'shipment_order_id' => ShipmentOrderModel::factory(),
            'item_id' => $this->faker->optional()->numberBetween(1000, 999999),
            'variation_id' => $this->faker->optional()->numberBetween(1, 999999),
            'sku' => strtoupper($this->faker->bothify('SKU-####')),
            'description' => $this->faker->sentence(3),
            'quantity' => $this->faker->numberBetween(1, 5),
            'packaging_profile_id' => $this->faker->boolean(70) ? FulfillmentPackagingProfileModel::factory() : null,
            'weight_kg' => $this->faker->optional()->randomFloat(2, 0.1, 12),
            'is_assembly' => $this->faker->boolean(15),
            'metadata' => [
                'vat_rate' => $this->faker->randomElement([7, 19]),
            ],
        ];
    }
}
