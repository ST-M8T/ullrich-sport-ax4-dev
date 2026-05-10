<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentPackagingProfileModel>
 */
final class FulfillmentPackagingProfileModelFactory extends Factory
{
    protected $model = FulfillmentPackagingProfileModel::class;

    public function definition(): array
    {
        return [
            'package_name' => $this->faker->unique()->words(2, true),
            'packaging_code' => strtoupper($this->faker->unique()->bothify('PKG-###')),
            'length_mm' => $this->faker->numberBetween(120, 1200),
            'width_mm' => $this->faker->numberBetween(80, 800),
            'height_mm' => $this->faker->numberBetween(60, 600),
            'truck_slot_units' => $this->faker->numberBetween(1, 4),
            'max_units_per_pallet_same_recipient' => $this->faker->numberBetween(1, 40),
            'max_units_per_pallet_mixed_recipient' => $this->faker->numberBetween(1, 30),
            'max_stackable_pallets_same_recipient' => $this->faker->numberBetween(1, 4),
            'max_stackable_pallets_mixed_recipient' => $this->faker->numberBetween(1, 3),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
