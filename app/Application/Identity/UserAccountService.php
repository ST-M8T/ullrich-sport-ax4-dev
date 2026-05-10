<?php

namespace App\Application\Identity;

use App\Domain\Identity\User;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Support\Security\SecurityContext;

final class UserAccountService
{
    public function __construct(
        private readonly UserCreationService $creator,
        private readonly UserPasswordService $passwords,
        private readonly UserUpdateService $updates,
    ) {}

    public function createUser(
        string $username,
        string $plainPassword,
        string $role = 'user',
        ?string $displayName = null,
        ?string $email = null,
        bool $requirePasswordChange = true,
        bool $disabled = false,
        ?SecurityContext $context = null,
    ): User {
        return $this->creator->create(
            $username,
            $plainPassword,
            $role,
            $displayName,
            $email,
            $requirePasswordChange,
            $disabled,
            $context,
        );
    }

    public function changePassword(
        Identifier $userId,
        string $plainPassword,
        bool $requireChange = false,
        ?SecurityContext $context = null
    ): void {
        $this->passwords->changePassword($userId, $plainPassword, $requireChange, $context);
    }

    public function setDisabled(Identifier $userId, bool $disabled, ?SecurityContext $context = null): void
    {
        $this->updates->setDisabled($userId, $disabled, $context);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function updateUser(Identifier $userId, array $attributes, ?SecurityContext $context = null): ?User
    {
        return $this->updates->update($userId, $attributes, $context);
    }
}
