<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchScanModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DispatchScanModel>
 */
final class DispatchScanModelFactory extends Factory
{
    protected $model = DispatchScanModel::class;

    public function definition(): array
    {
        return [
            'dispatch_list_id' => DispatchListModel::factory(),
            'barcode' => strtoupper($this->faker->bothify('BC########')),
            'shipment_order_id' => null,
            'captured_by_user_id' => $this->faker->boolean(40) ? UserModel::factory() : null,
            'captured_at' => $this->faker->dateTimeBetween('-6 hours', 'now'),
            'metadata' => [
                'lane' => $this->faker->randomElement(['A1', 'B2', 'C3']),
            ],
        ];
    }
}
