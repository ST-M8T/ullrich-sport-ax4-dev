<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserModel>
 */
final class UserModelFactory extends Factory
{
    protected $model = UserModel::class;

    public function definition(): array
    {
        return [
            'username' => Str::lower($this->faker->unique()->userName()),
            'display_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password_hash' => bcrypt('secret'),
            'role' => $this->faker->randomElement(['admin', 'user', 'viewer']),
            'must_change_password' => $this->faker->boolean(20),
            'disabled' => $this->faker->boolean(10),
            'last_login_at' => $this->faker->optional()->dateTimeBetween('-10 days', 'now'),
        ];
    }
}
