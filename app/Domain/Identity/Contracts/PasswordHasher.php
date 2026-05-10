<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\PasswordHash;

interface PasswordHasher
{
    public function hash(string $plain): PasswordHash;

    public function verify(string $plain, PasswordHash $hash): bool;
}
