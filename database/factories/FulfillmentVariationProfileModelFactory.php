<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentAssemblyOptionModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentVariationProfileModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentVariationProfileModel>
 */
final class FulfillmentVariationProfileModelFactory extends Factory
{
    protected $model = FulfillmentVariationProfileModel::class;

    public function definition(): array
    {
        $state = $this->faker->randomElement(['kit', 'assembled']);

        return [
            'item_id' => $this->faker->numberBetween(1000, 999999),
            'variation_id' => $this->faker->optional()->numberBetween(1, 999999),
            'variation_name' => $this->faker->optional()->words(3, true),
            'default_state' => $state,
            'default_packaging_id' => FulfillmentPackagingProfileModel::factory(),
            'default_weight_kg' => $this->faker->optional()->randomFloat(2, 0.2, 50),
            'assembly_option_id' => $state === 'assembled'
                ? FulfillmentAssemblyOptionModel::factory()
                : ($this->faker->boolean(30) ? FulfillmentAssemblyOptionModel::factory() : null),
        ];
    }
}
