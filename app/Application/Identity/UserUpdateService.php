<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Application\Identity\Authorization\RoleManager;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Identity\Contracts\UserRepository;
use App\Domain\Identity\User;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Support\Security\SecurityContext;
use DateTimeImmutable;

final class UserUpdateService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleManager $roles,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function setDisabled(Identifier $userId, bool $disabled, ?SecurityContext $context = null): void
    {
        $user = $this->users->getById($userId);
        if ($user === null) {
            return;
        }

        $this->users->disableUser($userId, $disabled);

        $actor = $this->resolveContext($context);
        $this->auditLogger->log(
            'identity.user.status_changed',
            $actor->actorType(),
            $actor->actorId(),
            $actor->actorName(),
            [
                'target_user_id' => $user->id()->toInt(),
                'username' => $user->username(),
                'disabled' => $disabled,
            ],
            $actor->ipAddress(),
            $actor->userAgent(),
        );
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function update(Identifier $userId, array $attributes, ?SecurityContext $context = null): ?User
    {
        $existing = $this->users->getById($userId);

        if ($existing === null) {
            return null;
        }

        $username = array_key_exists('username', $attributes)
            ? (string) $attributes['username']
            : $existing->username();

        $displayName = array_key_exists('display_name', $attributes)
            ? ($attributes['display_name'] !== null ? (string) $attributes['display_name'] : null)
            : $existing->displayName();

        $email = array_key_exists('email', $attributes)
            ? ($attributes['email'] !== null ? (string) $attributes['email'] : null)
            : $existing->email();

        $role = array_key_exists('role', $attributes)
            ? (string) $attributes['role']
            : $existing->role();
        $normalizedRole = $this->roles->ensureRoleExists($role);

        $mustChangePassword = array_key_exists('must_change_password', $attributes)
            ? (bool) $attributes['must_change_password']
            : $existing->mustChangePassword();

        $disabled = array_key_exists('disabled', $attributes)
            ? (bool) $attributes['disabled']
            : $existing->disabled();

        $updated = User::hydrate(
            $existing->id(),
            $username,
            $displayName,
            $email,
            $existing->passwordHash(),
            $normalizedRole,
            $mustChangePassword,
            $disabled,
            $existing->lastLoginAt(),
            $existing->createdAt(),
            new DateTimeImmutable,
            $this->roles->permissionsForRole($normalizedRole),
        );

        $this->users->save($updated);

        $actor = $this->resolveContext($context);

        $changes = array_filter([
            'username' => $existing->username() !== $updated->username() ? $updated->username() : null,
            'display_name' => $existing->displayName() !== $updated->displayName() ? $updated->displayName() : null,
            'email' => $existing->email() !== $updated->email() ? $updated->email() : null,
            'role' => $existing->role() !== $updated->role() ? $updated->role() : null,
            'must_change_password' => $existing->mustChangePassword() !== $updated->mustChangePassword()
                ? $updated->mustChangePassword()
                : null,
            'disabled' => $existing->disabled() !== $updated->disabled() ? $updated->disabled() : null,
        ], static fn ($value) => $value !== null);

        if (! empty($changes)) {
            $this->auditLogger->log(
                'identity.user.updated',
                $actor->actorType(),
                $actor->actorId(),
                $actor->actorName(),
                [
                    'target_user_id' => $updated->id()->toInt(),
                    'username' => $updated->username(),
                    'changes' => $changes,
                ],
                $actor->ipAddress(),
                $actor->userAgent(),
            );
        }

        return $this->users->getById($userId);
    }

    private function resolveContext(?SecurityContext $context): SecurityContext
    {
        return $context ?? SecurityContext::system('identity-service');
    }
}
