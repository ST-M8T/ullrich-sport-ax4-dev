<?php

namespace App\Infrastructure\Persistence\Configuration\Eloquent;

use App\Domain\Configuration\Contracts\SecretRotationRepository;
use App\Domain\Configuration\SystemSecretVersion;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;

final class EloquentSecretRotationRepository implements SecretRotationRepository
{
    use CastsDateTime;

    public function nextVersion(string $settingKey): int
    {
        $normalized = $this->normalizeKey($settingKey);

        $max = SystemSecretVersionModel::query()
            ->where('setting_key', $normalized)
            ->max('version');

        return ((int) $max) + 1;
    }

    public function record(SystemSecretVersion $version): void
    {
        SystemSecretVersionModel::query()->create([
            'setting_key' => $this->normalizeKey($version->settingKey()),
            'version' => $version->version(),
            'encrypted_value' => $version->encryptedValue(),
            'rotated_by_user_id' => $version->rotatedByUserId(),
            'rotated_at' => $version->rotatedAt(),
            'deactivated_at' => $version->deactivatedAt(),
        ]);
    }

    public function deactivateAllExcept(string $settingKey, int $version): void
    {
        $normalized = $this->normalizeKey($settingKey);
        $now = new DateTimeImmutable;

        SystemSecretVersionModel::query()
            ->where('setting_key', $normalized)
            ->where('version', '!=', $version)
            ->whereNull('deactivated_at')
            ->update(['deactivated_at' => $now]);
    }

    public function historyFor(string $settingKey, int $limit = 10): array
    {
        $normalized = $this->normalizeKey($settingKey);

        $records = SystemSecretVersionModel::query()
            ->where('setting_key', $normalized)
            ->orderByDesc('version')
            ->limit($limit)
            ->get();

        return $records->map(function (SystemSecretVersionModel $model): SystemSecretVersion {
            return SystemSecretVersion::hydrate(
                (int) $model->getKey(),
                $model->setting_key,
                (int) $model->version,
                $model->encrypted_value,
                $model->rotated_by_user_id ? (int) $model->rotated_by_user_id : null,
                $this->toImmutable($model->rotated_at) ?? new DateTimeImmutable,
                $this->toImmutable($model->deactivated_at),
            );
        })->all();
    }

    private function normalizeKey(string $key): string
    {
        return trim($key);
    }
}
