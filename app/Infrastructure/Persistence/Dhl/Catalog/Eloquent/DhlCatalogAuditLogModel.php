<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dhl\Catalog\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit log entry (`dhl_catalog_audit_log`).
 *
 * No `updated_at` column on purpose — audit rows are immutable.
 */
final class DhlCatalogAuditLogModel extends Model
{
    protected $table = 'dhl_catalog_audit_log';

    public const UPDATED_AT = null;

    protected $fillable = [
        'entity_type',
        'entity_key',
        'action',
        'actor',
        'diff',
        'created_at',
    ];

    protected $casts = [
        'diff' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
