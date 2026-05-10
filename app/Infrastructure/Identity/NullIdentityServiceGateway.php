<?php

namespace App\Infrastructure\Identity;

use App\Domain\Identity\Contracts\IdentityServiceGateway;

final class NullIdentityServiceGateway implements IdentityServiceGateway
{
    public function requiresTwoFactor(string $username): bool
    {
        return false;
    }

    public function verifyTwoFactorCode(string $username, string $code): bool
    {
        return true;
    }

    public function triggerPasswordReset(string $username): void
    {
        // Intentionally noop; no external identity provider configured.
    }

    public function notifyPasswordChanged(string $username): void
    {
        // Intentionally noop; no external identity provider configured.
    }
}
