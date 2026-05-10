<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentEventModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentEventModel>
 */
final class ShipmentEventModelFactory extends Factory
{
    protected $model = ShipmentEventModel::class;

    public function definition(): array
    {
        return [
            'shipment_id' => ShipmentModel::factory(),
            'event_code' => $this->faker->randomElement(['PU', 'DEP', 'ARR', 'DLV']),
            'event_status' => $this->faker->randomElement(['PICKED_UP', 'DEPARTED', 'ARRIVED', 'DELIVERED']),
            'event_description' => $this->faker->sentence(6),
            'facility' => $this->faker->optional()->company(),
            'city' => $this->faker->optional()->city(),
            'country_iso2' => strtoupper($this->faker->optional()->countryCode()),
            'event_occurred_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
            'payload' => [
                'raw' => $this->faker->sentence(),
            ],
            'created_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
        ];
    }
}
