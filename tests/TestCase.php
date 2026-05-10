<?php

namespace Tests;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\Support\CreatesDomainData;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use CreatesDomainData;
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function signInWithRole(string $role, array $overrides = []): UserModel
    {
        $username = $overrides['username'] ?? strtolower($role).'_'.Str::lower(Str::random(8));

        $attributes = array_merge(
            [
                'username' => $username,
                'display_name' => $overrides['display_name'] ?? Str::headline($username),
                'email' => $overrides['email'] ?? $username.'@example.test',
                'password_hash' => $overrides['password_hash'] ?? bcrypt('password'),
                'role' => $role,
                'must_change_password' => false,
                'disabled' => false,
            ],
            $overrides,
        );

        $user = UserModel::query()->create($attributes);
        $this->actingAs($user);

        return $user;
    }
}
