<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent persistence representation of `dhl_additional_services`.
 */
final class DhlAdditionalServiceModel extends Model
{
    protected $table = 'dhl_additional_services';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'parameter_schema',
        'deprecated_at',
        'source',
        'synced_at',
    ];

    protected $casts = [
        'parameter_schema' => 'array',
        'deprecated_at' => 'immutable_datetime',
        'synced_at' => 'immutable_datetime',
    ];
}
