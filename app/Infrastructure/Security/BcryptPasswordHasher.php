<?php

namespace App\Infrastructure\Security;

use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\PasswordHash;
use Illuminate\Support\Facades\Hash;

final class BcryptPasswordHasher implements PasswordHasher
{
    public function hash(string $plain): PasswordHash
    {
        return PasswordHash::fromString(Hash::make($plain));
    }

    public function verify(string $plain, PasswordHash $hash): bool
    {
        return Hash::check($plain, $hash->toString());
    }
}
