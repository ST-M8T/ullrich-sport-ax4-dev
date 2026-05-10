<?php

namespace App\Application\Configuration;

use App\Application\Monitoring\AuditLogger;
use App\Domain\Configuration\Contracts\SystemSettingRepository;
use App\Domain\Configuration\SystemSetting;
use App\Support\Security\SecurityContext;
use DateTimeImmutable;

final class SystemSettingService
{
    public function __construct(
        private readonly SystemSettingRepository $settings,
        private readonly SecretRotationService $secretRotation,
        private readonly AuditLogger $auditLogger,
        private readonly SecretEncryptionService $secretEncryption,
    ) {}

    /**
     * @return array<int,SystemSetting>
     */
    public function all(): array
    {
        $items = $this->settings->all();
        $normalized = is_array($items) ? array_values($items) : iterator_to_array($items, false);

        return array_map(fn (SystemSetting $setting) => $this->resolveSecretValue($setting), $normalized);
    }

    public function set(
        string $key,
        ?string $value,
        string $valueType = 'string',
        ?int $userId = null,
        ?SecurityContext $context = null
    ): void {
        $normalizedType = strtolower(trim($valueType));
        $actor = $context ?? SecurityContext::system('system-settings');

        $setting = SystemSetting::hydrate(
            $key,
            $value,
            $normalizedType,
            $userId,
            new DateTimeImmutable,
        );

        $storedValue = $normalizedType === 'secret'
            ? $this->secretEncryption->encrypt($value)
            : $value;

        if ($normalizedType === 'secret' && $storedValue !== null) {
            $this->secretRotation->rotate($key, $storedValue, $userId);
        }

        $persistedSetting = SystemSetting::hydrate(
            $setting->key(),
            $storedValue,
            $setting->valueType(),
            $setting->updatedByUserId(),
            $setting->updatedAt(),
        );

        $this->settings->upsert($persistedSetting);

        $this->auditLogger->log(
            'configuration.setting.updated',
            $actor->actorType(),
            $actor->actorId(),
            $actor->actorName(),
            [
                'setting_key' => $key,
                'value_type' => $normalizedType,
                'updated_by_user_id' => $userId,
                'non_empty' => $value !== null && $value !== '',
            ],
            $actor->ipAddress(),
            $actor->userAgent(),
        );
    }

    public function get(string $key, ?string $default = null, bool $revealSecret = true): ?string
    {
        $setting = $this->settings->get($key);

        if ($setting === null) {
            return $default;
        }

        $resolved = $this->resolveSecretValue($setting);

        if ($setting->isSecret()) {
            return $revealSecret ? ($resolved->rawValue() ?? $default) : $default;
        }

        return $resolved->rawValue() ?? $default;
    }

    public function remove(string $key, ?SecurityContext $context = null): void
    {
        $actor = $context ?? SecurityContext::system('system-settings');

        $this->settings->delete($key);

        $this->auditLogger->log(
            'configuration.setting.deleted',
            $actor->actorType(),
            $actor->actorId(),
            $actor->actorName(),
            ['setting_key' => $key],
            $actor->ipAddress(),
            $actor->userAgent(),
        );
    }

    /**
     * @return array<int,\App\Domain\Configuration\SystemSecretVersion>
     */
    public function secretHistory(string $key, int $limit = 10): array
    {
        return $this->secretRotation->history($key, $limit);
    }

    public function maskValue(SystemSetting $setting): ?string
    {
        $resolved = $this->resolveSecretValue($setting);

        if (! $resolved->isSecret()) {
            return $resolved->rawValue();
        }

        return $resolved->rawValue() !== null ? '••••••' : null;
    }

    private function resolveSecretValue(SystemSetting $setting): SystemSetting
    {
        if (! $setting->isSecret()) {
            return $setting;
        }

        $value = $setting->rawValue();

        if ($value === null || $value === '') {
            return $setting;
        }

        $decrypted = $this->secretEncryption->decrypt($value);

        return SystemSetting::hydrate(
            $setting->key(),
            $decrypted,
            $setting->valueType(),
            $setting->updatedByUserId(),
            $setting->updatedAt(),
        );
    }
}
