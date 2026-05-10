<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentOrderModel>
 */
final class ShipmentOrderModelFactory extends Factory
{
    protected $model = ShipmentOrderModel::class;

    public function definition(): array
    {
        $isBooked = $this->faker->boolean(40);

        return [
            'external_order_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'customer_number' => $this->faker->optional()->numberBetween(10000, 99999),
            'plenty_order_id' => $this->faker->optional()->numberBetween(10000, 99999),
            'order_type' => $this->faker->randomElement(['B2C', 'B2B', 'Bulk']),
            'sender_profile_id' => null,
            'sender_code' => $this->faker->optional()->bothify('SND-###'),
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'destination_country' => strtoupper($this->faker->countryCode()),
            'currency' => $this->faker->randomElement(['EUR', 'USD', 'CHF']),
            'total_amount' => $this->faker->randomFloat(2, 10, 5000),
            'processed_at' => $this->faker->optional()->dateTimeBetween('-10 days', 'now'),
            'is_booked' => $isBooked,
            'booked_at' => $isBooked ? $this->faker->dateTimeBetween('-5 days', 'now') : null,
            'booked_by' => $isBooked ? $this->faker->name() : null,
            'shipped_at' => $this->faker->optional()->dateTimeBetween('-3 days', 'now'),
            'last_export_filename' => $this->faker->optional()->lexify('export-????.csv'),
            'metadata' => [
                'channel' => $this->faker->randomElement(['plenty', 'manual']),
                'priority' => $this->faker->randomElement(['normal', 'express']),
            ],
        ];
    }
}
