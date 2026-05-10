<?php

namespace Tests\Support\Fakes;

use App\Domain\Identity\Contracts\IdentityServiceGateway;

final class FakeIdentityServiceGateway implements IdentityServiceGateway
{
    public bool $requiresTwoFactor = false;

    /** @var array<string,bool> */
    public array $validCodes = [];

    /** @var array<string,list<string>> */
    public array $notifications = [
        'password_reset' => [],
        'password_changed' => [],
    ];

    public function requiresTwoFactor(string $username): bool
    {
        return $this->requiresTwoFactor;
    }

    public function verifyTwoFactorCode(string $username, string $code): bool
    {
        if (! $this->requiresTwoFactor) {
            return true;
        }

        $key = strtolower($username.'|'.$code);

        return $this->validCodes[$key] ?? false;
    }

    public function triggerPasswordReset(string $username): void
    {
        $this->notifications['password_reset'][] = $username;
    }

    public function notifyPasswordChanged(string $username): void
    {
        $this->notifications['password_changed'][] = $username;
    }

    public function allowCode(string $username, string $code): void
    {
        $this->validCodes[strtolower($username.'|'.$code)] = true;
    }
}
