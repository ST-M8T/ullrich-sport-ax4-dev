<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Identity\Eloquent\LoginAttemptModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginAttemptModel>
 */
final class LoginAttemptModelFactory extends Factory
{
    protected $model = LoginAttemptModel::class;

    public function definition(): array
    {
        $success = $this->faker->boolean(80);

        return [
            'username' => $this->faker->userName(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'success' => $success,
            'failure_reason' => $success ? null : $this->faker->sentence(),
            'created_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
        ];
    }
}
