<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent persistence representation of `dhl_products`.
 *
 * Engineering-Handbuch §12: no domain logic, no event hooks, no fachlich
 * benannte Scopes. Domain mapping is the sole responsibility of
 * {@see \App\Infrastructure\Persistence\Dhl\Catalog\Mappers\DhlCatalogPersistenceMapper}.
 */
final class DhlProductModel extends Model
{
    protected $table = 'dhl_products';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'market_availability',
        'from_countries',
        'to_countries',
        'allowed_package_types',
        'weight_min_kg',
        'weight_max_kg',
        'dim_max_l_cm',
        'dim_max_b_cm',
        'dim_max_h_cm',
        'valid_from',
        'valid_until',
        'deprecated_at',
        'replaced_by_code',
        'source',
        'synced_at',
    ];

    protected $casts = [
        'from_countries' => 'array',
        'to_countries' => 'array',
        'allowed_package_types' => 'array',
        'weight_min_kg' => 'float',
        'weight_max_kg' => 'float',
        'dim_max_l_cm' => 'float',
        'dim_max_b_cm' => 'float',
        'dim_max_h_cm' => 'float',
        'valid_from' => 'immutable_datetime',
        'valid_until' => 'immutable_datetime',
        'deprecated_at' => 'immutable_datetime',
        'synced_at' => 'immutable_datetime',
    ];
}
