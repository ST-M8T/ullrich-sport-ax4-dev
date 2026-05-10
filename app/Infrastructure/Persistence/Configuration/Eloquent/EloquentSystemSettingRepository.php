<?php

namespace App\Infrastructure\Persistence\Configuration\Eloquent;

use App\Domain\Configuration\Contracts\SystemSettingRepository;
use App\Domain\Configuration\SystemSetting;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;

final class EloquentSystemSettingRepository implements SystemSettingRepository
{
    use CastsDateTime;

    public function upsert(SystemSetting $setting): void
    {
        SystemSettingModel::updateOrCreate(
            ['setting_key' => $setting->key()],
            [
                'setting_value' => $setting->rawValue(),
                'value_type' => $setting->valueType(),
                'updated_by_user_id' => $setting->updatedByUserId(),
                'updated_at' => $setting->updatedAt(),
            ]
        );
    }

    public function get(string $key): ?SystemSetting
    {
        $model = SystemSettingModel::find(trim($key));
        if (! $model) {
            return null;
        }

        return $this->mapModel($model);
    }

    public function delete(string $key): void
    {
        SystemSettingModel::query()
            ->where('setting_key', trim($key))
            ->delete();
    }

    public function all(): iterable
    {
        return SystemSettingModel::query()
            ->orderBy('setting_key')
            ->get()
            ->map(fn (SystemSettingModel $model) => $this->mapModel($model));
    }

    private function mapModel(SystemSettingModel $model): SystemSetting
    {
        return SystemSetting::hydrate(
            $model->setting_key,
            $model->setting_value,
            $model->value_type,
            $model->updated_by_user_id !== null ? (int) $model->updated_by_user_id : null,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }
}
