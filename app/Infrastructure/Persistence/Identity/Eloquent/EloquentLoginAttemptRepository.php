<?php

namespace App\Infrastructure\Persistence\Identity\Eloquent;

use App\Domain\Identity\Contracts\LoginAttemptRepository;
use App\Domain\Identity\LoginAttempt;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;

final class EloquentLoginAttemptRepository implements LoginAttemptRepository
{
    use CastsDateTime;

    public function nextIdentity(): Identifier
    {
        $next = (int) (LoginAttemptModel::query()->max('id') ?? 0) + 1;

        return Identifier::fromInt($next);
    }

    public function record(LoginAttempt $attempt): void
    {
        LoginAttemptModel::create([
            'id' => $attempt->id()->toInt(),
            'username' => $attempt->username(),
            'ip_address' => $attempt->ipAddress(),
            'user_agent' => $attempt->userAgent(),
            'success' => $attempt->success(),
            'failure_reason' => $attempt->failureReason(),
            'created_at' => $attempt->createdAt(),
        ]);
    }

    public function recentForUsername(string $username, int $limit = 10): iterable
    {
        return LoginAttemptModel::query()
            ->where('username', strtolower(trim($username)))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LoginAttemptModel $model) => $this->mapModel($model));
    }

    private function mapModel(LoginAttemptModel $model): LoginAttempt
    {
        return LoginAttempt::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->username,
            $model->ip_address,
            $model->user_agent,
            (bool) $model->success,
            $model->failure_reason,
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
        );
    }
}
