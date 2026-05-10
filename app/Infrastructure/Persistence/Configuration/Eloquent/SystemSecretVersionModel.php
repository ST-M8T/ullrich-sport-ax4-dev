<?php

namespace App\Infrastructure\Persistence\Configuration\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $setting_key
 * @property int $version
 * @property string|null $encrypted_value
 * @property int|null $rotated_by_user_id
 * @property string $rotated_at
 * @property string|null $deactivated_at
 */
final class SystemSecretVersionModel extends Model
{
    protected $table = 'system_secret_versions';

    protected $fillable = [
        'setting_key',
        'version',
        'encrypted_value',
        'rotated_by_user_id',
        'rotated_at',
        'deactivated_at',
    ];

    protected $casts = [
        'rotated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];
}
