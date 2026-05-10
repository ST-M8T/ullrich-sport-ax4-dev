<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Application\Identity\Authorization\RoleManager;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\Contracts\UserRepository;
use App\Domain\Identity\User;
use App\Support\Security\SecurityContext;
use DateTimeImmutable;

final class UserCreationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly RoleManager $roles,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(
        string $username,
        string $plainPassword,
        ?string $role = null,
        ?string $displayName = null,
        ?string $email = null,
        bool $requirePasswordChange = true,
        bool $disabled = false,
        ?SecurityContext $context = null,
    ): User {
        // Wenn keine Rolle übergeben wurde, fällt das System auf die in
        // config/identity.php -> defaults.role konfigurierte Rolle zurück.
        // Das entkoppelt Aufrufer von einer hartcodierten Default-Rolle.
        $resolvedRole = $role ?? $this->roles->defaultRole() ?? '';
        $normalizedRole = $this->roles->ensureRoleExists($resolvedRole);
        $id = $this->users->nextIdentity();
        $now = new DateTimeImmutable;

        $user = User::hydrate(
            $id,
            $username,
            $displayName,
            $email,
            $this->hasher->hash($plainPassword),
            $normalizedRole,
            $requirePasswordChange,
            $disabled,
            null,
            $now,
            $now,
            $this->roles->permissionsForRole($normalizedRole),
        );

        $this->users->save($user);

        $actor = $this->resolveContext($context);
        $this->auditLogger->log(
            'identity.user.created',
            $actor->actorType(),
            $actor->actorId(),
            $actor->actorName(),
            [
                'target_user_id' => $user->id()->toInt(),
                'username' => $user->username(),
                'role' => $user->role(),
                'must_change_password' => $user->mustChangePassword(),
                'disabled' => $user->disabled(),
            ],
            $actor->ipAddress(),
            $actor->userAgent(),
        );

        return $user;
    }

    private function resolveContext(?SecurityContext $context): SecurityContext
    {
        return $context ?? SecurityContext::system('identity-service');
    }
}
