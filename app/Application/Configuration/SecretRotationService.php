<?php

namespace App\Application\Configuration;

use App\Domain\Configuration\Contracts\SecretRotationRepository;
use App\Domain\Configuration\SystemSecretVersion;
use DateTimeImmutable;

class SecretRotationService
{
    public function __construct(private readonly SecretRotationRepository $repository) {}

    public function rotate(string $settingKey, ?string $encryptedValue, ?int $userId = null): void
    {
        $version = max(1, $this->repository->nextVersion($settingKey));

        $entry = SystemSecretVersion::hydrate(
            0,
            $settingKey,
            $version,
            $encryptedValue,
            $userId,
            new DateTimeImmutable,
            null,
        );

        $this->repository->record($entry);
        $this->repository->deactivateAllExcept($settingKey, $version);
    }

    /**
     * @return array<int,SystemSecretVersion>
     */
    public function history(string $settingKey, int $limit = 10): array
    {
        return $this->repository->historyFor($settingKey, $limit);
    }
}
