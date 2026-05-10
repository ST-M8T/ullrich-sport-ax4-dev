<?php

namespace App\Application\Identity\Queries;

use App\Domain\Identity\Contracts\UserRepository;
use App\Domain\Identity\User;
use App\Domain\Shared\ValueObjects\Identifier;

final class GetUserById
{
    public function __construct(private readonly UserRepository $users) {}

    public function __invoke(Identifier $id): ?User
    {
        return $this->users->getById($id);
    }
}
