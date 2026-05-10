<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dispatch\Eloquent;

use Database\Factories\DispatchScanModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property \DateTimeInterface|null $captured_at
 * @property \DateTimeInterface|null $created_at
 * @property \DateTimeInterface|null $updated_at
 */
class DispatchScanModel extends Model
{
    /** @use HasFactory<\Database\Factories\DispatchScanModelFactory> */
    use HasFactory;

    protected $table = 'dispatch_scans';

    protected $fillable = [
        'dispatch_list_id',
        'barcode',
        'shipment_order_id',
        'captured_by_user_id',
        'captured_at',
        'metadata',
    ];

    protected $casts = [
        'dispatch_list_id' => 'integer',
        'shipment_order_id' => 'integer',
        'captured_by_user_id' => 'integer',
        'captured_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(static function (self $model): void {
            DispatchSequenceModel::syncToAtLeast(
                DispatchSequenceModel::SCAN_SEQUENCE,
                (int) $model->getKey() + 1,
            );
        });
    }

    protected static function newFactory(): DispatchScanModelFactory
    {
        return DispatchScanModelFactory::new();
    }
}
