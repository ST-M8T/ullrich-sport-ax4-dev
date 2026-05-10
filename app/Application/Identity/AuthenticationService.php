<?php

namespace App\Application\Identity;

use App\Domain\Identity\Contracts\IdentityServiceGateway;
use App\Domain\Identity\Contracts\LoginAttemptRepository;
use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\Contracts\UserRepository;
use App\Domain\Identity\LoginAttempt;
use App\Domain\Identity\User;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AuthenticationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginAttemptRepository $attempts,
        private readonly PasswordHasher $hasher,
        private readonly IdentityServiceGateway $identityGateway,
    ) {}

    /**
     * @return array{
     *     success:bool,
     *     user?:User,
     *     requires_password_change?:bool,
     *     error?:string,
     *     retry_after_seconds?:int,
     *     two_factor_required?:bool
     * }
     */
    public function attempt(
        string $username,
        string $password,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $twoFactorCode = null
    ): array {
        $throttleKey = $this->throttleKey($username, $ip);
        $maxAttempts = max(1, (int) config('security.rate_limiting.login.max_attempts', 5));
        $decaySeconds = max(1, (int) config('security.rate_limiting.login.decay_seconds', 600));

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $this->recordAttempt(
                $this->attempts->nextIdentity(),
                $username,
                $ip,
                $userAgent,
                false,
                'rate_limited'
            );

            return [
                'success' => false,
                'error' => 'too_many_attempts',
                'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
                'two_factor_required' => false,
            ];
        }

        $user = $this->users->getByUsername($username);
        $attemptId = $this->attempts->nextIdentity();

        if (! $user || ! $user->canLogin() || ! $this->hasher->verify($password, $user->passwordHash())) {
            $this->recordAttempt($attemptId, $username, $ip, $userAgent, false, 'invalid_credentials');
            RateLimiter::hit($throttleKey, $decaySeconds);

            return ['success' => false, 'error' => 'authentication_failed'];
        }

        $requiresTwoFactor = $this->identityGateway->requiresTwoFactor($user->username());
        if ($requiresTwoFactor) {
            if ($twoFactorCode === null || trim($twoFactorCode) === '') {
                $this->recordAttempt($attemptId, $username, $ip, $userAgent, false, 'two_factor_required');
                RateLimiter::hit($throttleKey, $decaySeconds);

                return [
                    'success' => false,
                    'error' => 'two_factor_required',
                    'two_factor_required' => true,
                ];
            }

            if (! $this->identityGateway->verifyTwoFactorCode($user->username(), $twoFactorCode)) {
                $this->recordAttempt($attemptId, $username, $ip, $userAgent, false, 'two_factor_invalid');
                RateLimiter::hit($throttleKey, $decaySeconds);

                return [
                    'success' => false,
                    'error' => 'two_factor_invalid',
                    'two_factor_required' => true,
                ];
            }
        }

        $this->recordAttempt($attemptId, $username, $ip, $userAgent, true, null);
        $this->users->updateLastLogin($user->id(), new DateTimeImmutable);
        RateLimiter::clear($throttleKey);

        if ($user->requiresPasswordChange()) {
            return [
                'success' => true,
                'user' => $user,
                'requires_password_change' => true,
                'two_factor_required' => $requiresTwoFactor,
            ];
        }

        return [
            'success' => true,
            'user' => $user,
            'two_factor_required' => $requiresTwoFactor,
        ];
    }

    private function recordAttempt(
        Identifier $attemptId,
        string $username,
        ?string $ip,
        ?string $userAgent,
        bool $success,
        ?string $failureReason
    ): void {
        $attempt = LoginAttempt::hydrate(
            $attemptId,
            $username,
            $ip,
            $userAgent,
            $success,
            $failureReason,
            new DateTimeImmutable,
        );

        $this->attempts->record($attempt);
    }

    private function throttleKey(string $username, ?string $ip): string
    {
        $identifier = Str::lower(trim($username));
        $ipAddress = $ip ?: 'unknown';

        if ($identifier === '') {
            return sprintf('login:ip:%s', $ipAddress);
        }

        return sprintf('login:%s|%s', $identifier, $ipAddress);
    }
}
