<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentFreightProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentModel>
 */
final class ShipmentModelFactory extends Factory
{
    protected $model = ShipmentModel::class;

    public function definition(): array
    {
        return [
            'carrier_code' => $this->faker->randomElement(['dhl', 'ups', 'dpd']),
            'shipping_profile_id' => fn () => FulfillmentFreightProfileModel::factory()->create()->shipping_profile_id,
            'tracking_number' => strtoupper($this->faker->bothify('TRK########')),
            'status_code' => $this->faker->randomElement(['CREATED', 'IN_TRANSIT', 'DELIVERED']),
            'status_description' => $this->faker->sentence(3),
            'last_event_at' => $this->faker->optional()->dateTimeBetween('-2 days', 'now'),
            'delivered_at' => $this->faker->optional()->dateTimeBetween('-1 days', 'now'),
            'next_sync_after' => $this->faker->optional()->dateTimeBetween('now', '+2 hours'),
            'weight_kg' => $this->faker->optional()->randomFloat(2, 0.5, 40),
            'volume_dm3' => $this->faker->optional()->randomFloat(2, 1, 500),
            'pieces_count' => $this->faker->optional()->numberBetween(1, 5),
            'failed_attempts' => $this->faker->numberBetween(0, 3),
            'last_payload' => [
                'synced_at' => $this->faker->dateTimeBetween('-1 hours', 'now')->format(DATE_ATOM),
            ],
            'metadata' => [
                'source' => $this->faker->randomElement(['dhl-api', 'manual']),
            ],
        ];
    }
}
