<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dispatch\Eloquent;

use Database\Factories\DispatchListModelFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int|null $total_orders
 * @property int|null $total_packages
 * @property int|null $total_truck_slots
 * @property-read DispatchMetricsModel|null $metrics
 * @property-read Collection<int, DispatchScanModel> $scans
 * @property \DateTimeInterface|null $close_requested_at
 * @property \DateTimeInterface|null $closed_at
 * @property \DateTimeInterface|null $exported_at
 * @property \DateTimeInterface|null $created_at
 * @property \DateTimeInterface|null $updated_at
 */
class DispatchListModel extends Model
{
    /** @use HasFactory<\Database\Factories\DispatchListModelFactory> */
    use HasFactory;

    protected $table = 'dispatch_lists';

    protected $fillable = [
        'reference',
        'title',
        'status',
        'created_by_user_id',
        'closed_by_user_id',
        'close_requested_at',
        'close_requested_by',
        'closed_at',
        'exported_at',
        'export_filename',
        'total_packages',
        'total_orders',
        'total_truck_slots',
        'notes',
    ];

    protected $casts = [
        'created_by_user_id' => 'integer',
        'closed_by_user_id' => 'integer',
        'close_requested_at' => 'datetime',
        'closed_at' => 'datetime',
        'exported_at' => 'datetime',
        'total_packages' => 'integer',
        'total_orders' => 'integer',
        'total_truck_slots' => 'integer',
    ];

    /**
     * @return HasMany<DispatchScanModel, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(DispatchScanModel::class, 'dispatch_list_id');
    }

    /**
     * @return HasOne<DispatchMetricsModel, $this>
     */
    public function metrics(): HasOne
    {
        return $this->hasOne(DispatchMetricsModel::class, 'dispatch_list_id');
    }

    protected static function booted(): void
    {
        static::created(static function (self $model): void {
            DispatchSequenceModel::syncToAtLeast(
                DispatchSequenceModel::LIST_SEQUENCE,
                (int) $model->getKey() + 1,
            );
        });
    }

    protected static function newFactory(): DispatchListModelFactory
    {
        return DispatchListModelFactory::new();
    }
}
