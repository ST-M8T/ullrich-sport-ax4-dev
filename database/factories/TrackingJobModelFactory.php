<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingJobModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingJobModel>
 */
final class TrackingJobModelFactory extends Factory
{
    protected $model = TrackingJobModel::class;

    public function configure(): static
    {
        return $this->afterMaking(function (TrackingJobModel $model): void {
            $scheduled = $model->scheduled_at ?? now();

            if ($model->created_at === null || $model->created_at > $scheduled) {
                $model->created_at = (clone $scheduled)->subMinute();
            }

            $referenceTime = $model->finished_at
                ?? $model->started_at
                ?? $scheduled;

            if ($model->updated_at === null || $model->updated_at < $model->created_at) {
                $model->updated_at = $referenceTime;
            }
        });
    }

    public function definition(): array
    {
        $status = $this->faker->randomElement(['scheduled', 'running', 'completed', 'failed']);
        $scheduled = $this->faker->dateTimeBetween('-1 day', 'now');
        $createdAt = $this->faker->dateTimeBetween('-2 days', $scheduled);
        $started = in_array($status, ['running', 'completed', 'failed'], true)
            ? $this->faker->dateTimeBetween($scheduled, 'now')
            : null;
        $finished = in_array($status, ['completed', 'failed'], true)
            ? $this->faker->dateTimeBetween($started ?? $scheduled, 'now')
            : null;

        return [
            'job_type' => $this->faker->randomElement(['dhl-sync', 'plenty-sync', 'alert-dispatch']),
            'status' => $status,
            'scheduled_at' => $scheduled,
            'started_at' => $started,
            'finished_at' => $finished,
            'attempt' => $this->faker->numberBetween(0, 3),
            'last_error' => $status === 'failed' ? $this->faker->sentence() : null,
            'payload' => [
                'cursor' => $this->faker->numberBetween(1, 9999),
            ],
            'result' => $status === 'completed' ? ['processed' => $this->faker->numberBetween(1, 25)] : [],
            'created_at' => $createdAt,
            'updated_at' => $finished ?? $started ?? $scheduled,
        ];
    }
}
