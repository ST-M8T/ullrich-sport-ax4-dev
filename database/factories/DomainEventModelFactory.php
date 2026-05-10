<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Monitoring\Eloquent\DomainEventModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DomainEventModel>
 */
final class DomainEventModelFactory extends Factory
{
    protected $model = DomainEventModel::class;

    public function definition(): array
    {
        $occurred = $this->faker->dateTimeBetween('-2 days', 'now');

        return [
            'id' => (string) Str::uuid(),
            'event_name' => $this->faker->randomElement([
                'dispatch.list.closed',
                'tracking.job.finished',
                'configuration.setting.updated',
            ]),
            'aggregate_type' => $this->faker->randomElement(['dispatch_list', 'tracking_job', 'system_setting']),
            'aggregate_id' => (string) $this->faker->numberBetween(1, 99999),
            'payload' => [
                'actor' => $this->faker->randomElement(['system', 'user']),
            ],
            'metadata' => [
                'source' => $this->faker->randomElement(['ui', 'cli', 'api']),
            ],
            'occurred_at' => $occurred,
            'created_at' => $this->faker->dateTimeBetween($occurred, 'now'),
        ];
    }
}
