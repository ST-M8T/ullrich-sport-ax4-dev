<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchMetricsModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DispatchMetricsModel>
 */
final class DispatchMetricsModelFactory extends Factory
{
    protected $model = DispatchMetricsModel::class;

    public function definition(): array
    {
        $orders = $this->faker->numberBetween(1, 40);
        $packages = max($orders, $this->faker->numberBetween($orders, $orders + 20));
        $items = $packages * $this->faker->numberBetween(1, 5);

        return [
            'dispatch_list_id' => DispatchListModel::factory(),
            'total_orders' => $orders,
            'total_packages' => $packages,
            'total_items' => $items,
            'total_truck_slots' => $this->faker->numberBetween(1, 20),
            'metrics' => [
                'by_sender' => [
                    'default' => $orders,
                ],
            ],
        ];
    }
}
