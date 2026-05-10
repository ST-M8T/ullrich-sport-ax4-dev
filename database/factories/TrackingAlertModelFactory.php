<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingAlertModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingAlertModel>
 */
final class TrackingAlertModelFactory extends Factory
{
    protected $model = TrackingAlertModel::class;

    public function definition(): array
    {
        $createdAt = $this->faker->dateTimeBetween('-12 hours', '-30 minutes');
        $sentAt = $this->faker->optional()->dateTimeBetween($createdAt, 'now');
        $acknowledgedAt = $sentAt ? $this->faker->optional()->dateTimeBetween($sentAt, 'now') : null;
        $updatedAt = $this->faker->dateTimeBetween($sentAt ?? $createdAt, 'now');

        return [
            'shipment_id' => $this->faker->optional()->numberBetween(1, 99999),
            'alert_type' => $this->faker->randomElement(['delivery.delay', 'exception', 'custom']),
            'severity' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'channel' => $this->faker->optional()->randomElement(['mail', 'slack', 'webhook']),
            'message' => $this->faker->sentence(8),
            'sent_at' => $sentAt,
            'acknowledged_at' => $acknowledgedAt,
            'metadata' => [
                'tracking_number' => strtoupper($this->faker->bothify('TRK########')),
            ],
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }
}
