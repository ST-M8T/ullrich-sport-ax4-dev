<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent persistence representation of
 * `dhl_product_service_assignments`.
 */
final class DhlProductServiceAssignmentModel extends Model
{
    protected $table = 'dhl_product_service_assignments';

    protected $fillable = [
        'product_code',
        'service_code',
        'from_country',
        'to_country',
        'payer_code',
        'requirement',
        'default_parameters',
        'source',
        'synced_at',
    ];

    protected $casts = [
        'default_parameters' => 'array',
        'synced_at' => 'immutable_datetime',
    ];
}
