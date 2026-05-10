<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentAssemblyOptionModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentAssemblyOptionModel>
 */
final class FulfillmentAssemblyOptionModelFactory extends Factory
{
    protected $model = FulfillmentAssemblyOptionModel::class;

    public function definition(): array
    {
        return [
            'assembly_item_id' => $this->faker->unique()->numberBetween(1000, 999999),
            'assembly_packaging_id' => FulfillmentPackagingProfileModel::factory(),
            'assembly_weight_kg' => $this->faker->optional()->randomFloat(2, 0.5, 35),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
