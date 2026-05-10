<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Monitoring\Eloquent\SystemJobModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemJobModel>
 */
final class SystemJobModelFactory extends Factory
{
    protected $model = SystemJobModel::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['running', 'queued', 'completed', 'failed']);
        $scheduledAt = $this->faker->optional()->dateTimeBetween('-1 day', 'now');
        $startedAt = in_array($status, ['running', 'completed', 'failed', 'queued'], true)
            ? $this->faker->dateTimeBetween($scheduledAt ?? '-1 hour', 'now')
            : null;
        $finishedAt = in_array($status, ['completed', 'failed'], true)
            ? $this->faker->dateTimeBetween($startedAt ?? 'now', 'now')
            : null;

        return [
            'job_name' => $this->faker->randomElement(['tracking-job', 'order-sync', 'data-export']),
            'job_type' => $this->faker->optional()->randomElement(['tracking', 'fulfillment', 'reporting']),
            'run_context' => $this->faker->optional()->randomElement(['dispatch', 'manual', 'schedule']),
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $finishedAt && $startedAt
                ? max(1, ($finishedAt->getTimestamp() - $startedAt->getTimestamp()) * 1000)
                : null,
            'payload' => [
                'context' => $this->faker->word(),
            ],
            'result' => $status === 'completed'
                ? ['processed' => $this->faker->numberBetween(1, 50)]
                : [],
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
        ];
    }
}
