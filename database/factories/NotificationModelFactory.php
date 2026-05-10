<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Configuration\Eloquent\NotificationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationModel>
 */
final class NotificationModelFactory extends Factory
{
    protected $model = NotificationModel::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'sent', 'failed']);
        $scheduledAt = $this->faker->optional()->dateTimeBetween('-1 day', 'now');
        $sentAt = $status === 'sent' ? $this->faker->dateTimeBetween($scheduledAt ?? '-1 hour', 'now') : null;

        return [
            'notification_type' => $this->faker->randomElement(['dispatch.test', 'tracking.alert', 'system.info']),
            'channel' => $this->faker->optional()->randomElement(['mail', 'slack', 'webhook']),
            'payload' => [
                'recipient' => $this->faker->safeEmail(),
                'data' => ['foo' => 'bar'],
            ],
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'sent_at' => $sentAt,
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
        ];
    }
}
