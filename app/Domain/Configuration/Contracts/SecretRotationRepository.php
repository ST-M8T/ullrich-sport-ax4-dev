<?php

declare(strict_types=1);

namespace App\Domain\Configuration\Contracts;

use App\Domain\Configuration\SystemSecretVersion;

interface SecretRotationRepository
{
    public function nextVersion(string $settingKey): int;

    public function record(SystemSecretVersion $version): void;

    public function deactivateAllExcept(string $settingKey, int $version): void;

    /**
     * @return array<int,SystemSecretVersion>
     */
    public function historyFor(string $settingKey, int $limit = 10): array;
}
