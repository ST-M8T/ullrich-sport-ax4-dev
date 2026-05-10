<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SystemSettingModel>
 */
final class SystemSettingModelFactory extends Factory
{
    protected $model = SystemSettingModel::class;

    public function definition(): array
    {
        return [
            'setting_key' => 'app.'.Str::snake($this->faker->unique()->word()),
            'setting_value' => $this->faker->sentence(),
            'value_type' => $this->faker->randomElement(['string', 'int', 'bool']),
            'updated_by_user_id' => null,
            'updated_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
