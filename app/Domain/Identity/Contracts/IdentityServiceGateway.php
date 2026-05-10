<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

interface IdentityServiceGateway
{
    public function requiresTwoFactor(string $username): bool;

    public function verifyTwoFactorCode(string $username, string $code): bool;

    public function triggerPasswordReset(string $username): void;

    public function notifyPasswordChanged(string $username): void;
}
