<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Application\Monitoring\AuditLogger;
use App\Domain\Identity\Contracts\IdentityServiceGateway;
use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\Contracts\UserRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Support\Security\SecurityContext;

final class UserPasswordService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly IdentityServiceGateway $identityGateway,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function changePassword(
        Identifier $userId,
        string $plainPassword,
        bool $requireChange = false,
        ?SecurityContext $context = null
    ): void {
        $user = $this->users->getById($userId);
        if ($user === null) {
            return;
        }

        $hash = $this->hasher->hash($plainPassword);
        $this->users->updatePassword($userId, $hash, $requireChange);
        $this->identityGateway->notifyPasswordChanged($user->username());

        $actor = $this->resolveContext($context);

        $this->auditLogger->log(
            'identity.user.password_changed',
            $actor->actorType(),
            $actor->actorId(),
            $actor->actorName(),
            [
                'target_user_id' => $user->id()->toInt(),
                'username' => $user->username(),
                'require_password_change' => $requireChange,
            ],
            $actor->ipAddress(),
            $actor->userAgent(),
        );
    }

    private function resolveContext(?SecurityContext $context): SecurityContext
    {
        return $context ?? SecurityContext::system('identity-service');
    }
}
