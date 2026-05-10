<?php

namespace App\Application\Identity\Queries;

use App\Domain\Identity\Contracts\LoginAttemptRepository;

final class ListRecentLoginAttempts
{
    public function __construct(private readonly LoginAttemptRepository $attempts) {}

    /**
     * @return iterable<\App\Domain\Identity\LoginAttempt>
     */
    public function __invoke(string $username, int $limit = 10): iterable
    {
        return $this->attempts->recentForUsername($username, $limit);
    }
}
