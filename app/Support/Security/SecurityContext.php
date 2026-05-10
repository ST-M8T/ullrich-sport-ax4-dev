<?php

namespace App\Support\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

final class SecurityContext
{
    public function __construct(
        private readonly string $actorType,
        private readonly ?string $actorId,
        private readonly ?string $actorName,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var Authenticatable|null $user */
        $user = $request->user();

        return new self(
            $user ? 'user' : 'guest',
            $user ? (string) $user->getAuthIdentifier() : null,
            self::resolveUserName($user),
            $request->ip(),
            $request->userAgent()
        );
    }

    public static function fromUser(?Authenticatable $user, ?Request $request = null): self
    {
        return new self(
            $user ? 'user' : 'system',
            $user ? (string) $user->getAuthIdentifier() : null,
            self::resolveUserName($user),
            $request?->ip(),
            $request?->userAgent()
        );
    }

    public static function system(string $name = 'system'): self
    {
        return new self('system', null, $name, null, null);
    }

    public function actorType(): string
    {
        return $this->actorType;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    public function actorName(): ?string
    {
        return $this->actorName;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string,mixed>
     */
    public function mergeContext(array $context = []): array
    {
        return array_merge([
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ], $context);
    }

    private static function resolveUserName(?Authenticatable $user): ?string
    {
        if (! $user) {
            return null;
        }

        // Authenticatable garantiert kein getAttributes() — wir greifen daher
        // defensiv auf die typischen Eloquent-Attribute zurück.
        /** @var array<string,mixed> $attributes */
        $attributes = $user instanceof \Illuminate\Database\Eloquent\Model
            ? (array) $user->getAttributes()
            : [];

        return $attributes['name'] ?? $attributes['username'] ?? $attributes['email'] ?? null;
    }
}
