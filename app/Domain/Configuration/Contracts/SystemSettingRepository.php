<?php

declare(strict_types=1);

namespace App\Domain\Configuration\Contracts;

use App\Domain\Configuration\SystemSetting;

interface SystemSettingRepository
{
    public function upsert(SystemSetting $setting): void;

    public function get(string $key): ?SystemSetting;

    public function delete(string $key): void;

    /**
     * @return iterable<SystemSetting>
     */
    public function all(): iterable;
}
