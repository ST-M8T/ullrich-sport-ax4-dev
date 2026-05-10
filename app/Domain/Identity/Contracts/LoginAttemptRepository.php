<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\LoginAttempt;
use App\Domain\Shared\ValueObjects\Identifier;

interface LoginAttemptRepository
{
    public function nextIdentity(): Identifier;

    public function record(LoginAttempt $attempt): void;

    /**
     * @return iterable<LoginAttempt>
     */
    public function recentForUsername(string $username, int $limit = 10): iterable;
}
