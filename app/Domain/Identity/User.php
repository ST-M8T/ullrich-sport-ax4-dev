<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class User
{
    private function __construct(
        private readonly Identifier $id,
        private readonly string $username,
        private readonly ?string $displayName,
        private readonly ?string $email,
        private readonly PasswordHash $passwordHash,
        private readonly string $role,
        private readonly bool $mustChangePassword,
        private readonly bool $disabled,
        private readonly ?DateTimeImmutable $lastLoginAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        /** @var array<int,string> */
        private readonly array $permissions,
    ) {}

    /**
     * @param  array<int, string>  $permissions
     */
    public static function hydrate(
        Identifier $id,
        string $username,
        ?string $displayName,
        ?string $email,
        PasswordHash $passwordHash,
        string $role,
        bool $mustChangePassword,
        bool $disabled,
        ?DateTimeImmutable $lastLoginAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        array $permissions = [],
    ): self {
        $normalizedPermissions = array_values(array_unique(array_map(
            static fn ($permission): string => strtolower(trim((string) $permission)),
            $permissions
        )));

        return new self(
            $id,
            strtolower(trim($username)),
            $displayName ? trim($displayName) : null,
            $email ? strtolower(trim($email)) : null,
            $passwordHash,
            strtolower(trim($role)),
            $mustChangePassword,
            $disabled,
            $lastLoginAt,
            $createdAt,
            $updatedAt,
            $normalizedPermissions,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function passwordHash(): PasswordHash
    {
        return $this->passwordHash;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function disabled(): bool
    {
        return $this->disabled;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function canLogin(): bool
    {
        return ! $this->disabled;
    }

    public function requiresPasswordChange(): bool
    {
        return $this->mustChangePassword;
    }

    /**
     * @return array<int,string>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool
    {
        $permission = strtolower(trim($permission));

        if (in_array('*', $this->permissions, true)) {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === strtolower(trim($role));
    }
}
