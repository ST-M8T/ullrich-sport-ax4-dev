<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row state tracker for the DHL catalog sync (consumed by PROJ-2).
 */
final class DhlCatalogSyncStatusModel extends Model
{
    protected $table = 'dhl_catalog_sync_status';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'last_attempt_at',
        'last_success_at',
        'last_error',
        'consecutive_failures',
        'mail_sent_for_failure_streak',
    ];

    protected $casts = [
        'last_attempt_at' => 'immutable_datetime',
        'last_success_at' => 'immutable_datetime',
        'consecutive_failures' => 'integer',
        'mail_sent_for_failure_streak' => 'boolean',
    ];
}
