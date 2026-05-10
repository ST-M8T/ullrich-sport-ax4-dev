<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders;

use Database\Factories\ShipmentModelFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \DateTimeInterface|null $last_event_at
 * @property \DateTimeInterface|null $delivered_at
 * @property \DateTimeInterface|null $next_sync_after
 * @property-read EloquentCollection<int, ShipmentEventModel> $events
 */
class ShipmentModel extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentModelFactory> */
    use HasFactory;

    protected $table = 'shipments';

    protected $fillable = [
        'carrier_code',
        'shipping_profile_id',
        'tracking_number',
        'status_code',
        'status_description',
        'last_event_at',
        'delivered_at',
        'next_sync_after',
        'weight_kg',
        'volume_dm3',
        'pieces_count',
        'failed_attempts',
        'last_payload',
        'metadata',
    ];

    protected $casts = [
        'shipping_profile_id' => 'integer',
        'weight_kg' => 'float',
        'volume_dm3' => 'float',
        'pieces_count' => 'integer',
        'failed_attempts' => 'integer',
        'last_event_at' => 'datetime',
        'delivered_at' => 'datetime',
        'next_sync_after' => 'datetime',
        'last_payload' => 'array',
        'metadata' => 'array',
    ];

    /**
     * @return HasMany<ShipmentEventModel, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEventModel::class, 'shipment_id');
    }

    protected static function newFactory(): ShipmentModelFactory
    {
        return ShipmentModelFactory::new();
    }
}
