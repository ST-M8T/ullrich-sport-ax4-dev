<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Monitoring\Eloquent\AuditLogModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLogModel>
 */
final class AuditLogModelFactory extends Factory
{
    protected $model = AuditLogModel::class;

    public function definition(): array
    {
        return [
            'actor_type' => $this->faker->randomElement(['user', 'system']),
            'actor_id' => $this->faker->optional()->numerify('##'),
            'actor_name' => $this->faker->optional()->name(),
            'action' => $this->faker->randomElement([
                'dispatch.list_closed',
                'tracking.job_scheduled',
                'configuration.setting_updated',
            ]),
            'context' => [
                'ip' => $this->faker->ipv4(),
            ],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
        ];
    }
}
