<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\PasswordHash;
use App\Domain\Identity\User;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

interface UserRepository
{
    public function nextIdentity(): Identifier;

    public function getById(Identifier $id): ?User;

    public function getByUsername(string $username): ?User;

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<User>
     */
    public function search(array $filters = []): iterable;

    public function save(User $user): void;

    public function updatePassword(Identifier $id, PasswordHash $hash, bool $mustChange = false): void;

    public function disableUser(Identifier $id, bool $disabled = true): void;

    public function updateLastLogin(Identifier $id, DateTimeImmutable $timestamp): void;
}
