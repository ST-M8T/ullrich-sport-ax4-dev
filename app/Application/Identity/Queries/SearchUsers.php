<?php

namespace App\Application\Identity\Queries;

use App\Domain\Identity\Contracts\UserRepository;

final class SearchUsers
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<\App\Domain\Identity\User>
     */
    public function __invoke(array $filters = []): iterable
    {
        return $this->users->search($filters);
    }
}
