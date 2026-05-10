<?php

namespace Database\Factories;

use App\Domain\Dispatch\DispatchList;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DispatchListModel>
 */
final class DispatchListModelFactory extends Factory
{
    protected $model = DispatchListModel::class;

    public function configure(): static
    {
        return $this->afterMaking(function (DispatchListModel $model): void {
            $model->status ??= DispatchList::STATUS_OPEN;
            $model->created_at ??= now()->subDay();
            $model->updated_at ??= $model->created_at;

            if ($model->status === DispatchList::STATUS_OPEN) {
                $model->closed_by_user_id = null;
                $model->closed_at = null;
                $model->exported_at = null;
                $model->export_filename = null;

                return;
            }

            if ($model->status === DispatchList::STATUS_CLOSED) {
                $model->closed_at ??= $model->updated_at;
                $model->closed_by_user_id ??= UserModel::factory()->create()->getKey();
                $model->exported_at = null;
                $model->export_filename = null;

                return;
            }

            if ($model->status === DispatchList::STATUS_EXPORTED) {
                $model->closed_at ??= $model->updated_at;
                $model->closed_by_user_id ??= UserModel::factory()->create()->getKey();
                $model->exported_at ??= $model->updated_at;
                $model->export_filename ??= strtolower(Str::random(12)).'.csv';
            }
        });
    }

    public function definition(): array
    {
        $status = $this->faker->randomElement(['open', 'closed', 'exported']);
        $closed = in_array($status, ['closed', 'exported'], true);
        $createdAt = $this->faker->dateTimeBetween('-5 days', '-3 days');
        $closeRequestedAt = $this->faker->optional(0.3)->dateTimeBetween($createdAt, '-1 day');
        $closedAt = $closed
            ? $this->faker->dateTimeBetween($closeRequestedAt ?? $createdAt, 'now')
            : null;
        $exportedAt = $status === 'exported'
            ? $this->faker->dateTimeBetween($closedAt ?? $createdAt, 'now')
            : null;

        $updatedAt = $this->faker->dateTimeBetween($closedAt ?? $createdAt, 'now');

        foreach ([$closeRequestedAt, $closedAt, $exportedAt] as $candidate) {
            if ($candidate !== null && $candidate > $updatedAt) {
                $updatedAt = clone $candidate;
            }
        }

        $createdByUser = $this->faker->boolean(50) ? UserModel::factory() : null;
        $closedByUser = $closed ? UserModel::factory() : null;

        return [
            'reference' => strtoupper(Str::random(8)),
            'title' => $this->faker->sentence(3),
            'status' => $status,
            'created_by_user_id' => $createdByUser,
            'closed_by_user_id' => $closedByUser,
            'close_requested_at' => $closeRequestedAt,
            'close_requested_by' => $closeRequestedAt ? $this->faker->name() : null,
            'closed_at' => $closedAt,
            'exported_at' => $exportedAt,
            'export_filename' => $status === 'exported' ? $this->faker->lexify('dispatch-????.csv') : null,
            'total_packages' => $this->faker->numberBetween(1, 60),
            'total_orders' => $this->faker->numberBetween(1, 40),
            'total_truck_slots' => $this->faker->numberBetween(1, 20),
            'notes' => $this->faker->optional()->sentence(),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }
}
