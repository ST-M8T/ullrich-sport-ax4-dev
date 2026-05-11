<?php

namespace App\Infrastructure\Persistence\Identity\Eloquent;

use App\Domain\Identity\Contracts\UserRepository;
use App\Domain\Identity\PasswordHash;
use App\Domain\Identity\User;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class EloquentUserRepository implements UserRepository
{
    public function nextIdentity(): Identifier
    {
        $next = (int) (UserModel::query()->max('id') ?? 0) + 1;

        return Identifier::fromInt($next);
    }

    public function getById(Identifier $id): ?User
    {
        $model = UserModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function getByUsername(string $username): ?User
    {
        $model = UserModel::query()->where('username', strtolower(trim($username)))->first();

        return $model ? $this->mapModel($model) : null;
    }

    public function getByEmail(string $email): ?User
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        $model = UserModel::query()->where('email', $normalized)->first();

        return $model ? $this->mapModel($model) : null;
    }

    public function search(array $filters = []): iterable
    {
        $query = UserModel::query()
            ->when(isset($filters['username']), function ($builder) use ($filters) {
                $username = strtolower(trim((string) $filters['username']));
                if ($username !== '') {
                    $builder->where('username', 'like', $username.'%');
                }
            })
            ->when(isset($filters['role']), function ($builder) use ($filters) {
                $role = strtolower(trim((string) $filters['role']));
                if ($role !== '') {
                    $builder->where('role', $role);
                }
            })
            ->when(array_key_exists('disabled', $filters), function ($builder) use ($filters) {
                $value = (bool) $filters['disabled'];
                $builder->where('disabled', $value);
            })
            ->when(array_key_exists('must_change_password', $filters), function ($builder) use ($filters) {
                $value = (bool) $filters['must_change_password'];
                $builder->where('must_change_password', $value);
            })
            ->orderBy('username');

        return $query->get()->map(fn (UserModel $model) => $this->mapModel($model));
    }

    public function save(User $user): void
    {
        $model = UserModel::find($user->id()->toInt()) ?? new UserModel(['id' => $user->id()->toInt()]);

        $model->username = $user->username();
        $model->display_name = $user->displayName();
        $model->email = $user->email();
        $model->password_hash = $user->passwordHash()->toString();
        $model->role = $user->role();
        $model->must_change_password = $user->mustChangePassword();
        $model->disabled = $user->disabled();
        $model->last_login_at = $user->lastLoginAt();
        $model->save();
    }

    public function updatePassword(Identifier $id, PasswordHash $hash, bool $mustChange = false): void
    {
        UserModel::query()
            ->where('id', $id->toInt())
            ->update([
                'password_hash' => $hash->toString(),
                'must_change_password' => $mustChange,
            ]);
    }

    public function disableUser(Identifier $id, bool $disabled = true): void
    {
        UserModel::query()->where('id', $id->toInt())->update(['disabled' => $disabled]);
    }

    public function updateLastLogin(Identifier $id, DateTimeImmutable $timestamp): void
    {
        UserModel::query()->where('id', $id->toInt())->update(['last_login_at' => $timestamp]);
    }

    private function mapModel(UserModel $model): User
    {
        return $model->toIdentityUser();
    }
}
