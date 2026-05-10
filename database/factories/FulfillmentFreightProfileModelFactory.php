<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentFreightProfileModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentFreightProfileModel>
 */
final class FulfillmentFreightProfileModelFactory extends Factory
{
    protected $model = FulfillmentFreightProfileModel::class;

    public function definition(): array
    {
        return [
            'shipping_profile_id' => $this->faker->unique()->numberBetween(1, 9999),
            'label' => $this->faker->optional()->words(3, true),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
