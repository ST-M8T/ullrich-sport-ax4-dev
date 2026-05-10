<?php

namespace App\Infrastructure\Persistence\Configuration\Eloquent;

use Database\Factories\SystemSettingModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSettingModel extends Model
{
    /** @use HasFactory<\Database\Factories\SystemSettingModelFactory> */
    use HasFactory;

    protected $table = 'system_settings';

    public $timestamps = false;

    protected $primaryKey = 'setting_key';

    public $incrementing = false;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'value_type',
        'updated_by_user_id',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
        'updated_by_user_id' => 'integer',
    ];

    protected static function newFactory(): SystemSettingModelFactory
    {
        return SystemSettingModelFactory::new();
    }
}
