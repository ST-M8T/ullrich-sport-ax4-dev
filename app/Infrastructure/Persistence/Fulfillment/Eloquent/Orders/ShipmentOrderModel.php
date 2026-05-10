<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders;

use Database\Factories\ShipmentOrderModelFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $external_order_id
 * @property int|null $sender_profile_id
 * @property \DateTimeInterface|null $processed_at
 * @property \DateTimeInterface|null $booked_at
 * @property \DateTimeInterface|null $shipped_at
 * @property \DateTimeInterface|null $dhl_booked_at
 * @property-read EloquentCollection<int, ShipmentOrderItemModel> $items
 * @property-read EloquentCollection<int, ShipmentPackageModel> $packages
 */
class ShipmentOrderModel extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentOrderModelFactory> */
    use HasFactory;

    protected $table = 'shipment_orders';

    protected $fillable = [
        'external_order_id',
        'customer_number',
        'plenty_order_id',
        'order_type',
        'sender_profile_id',
        'sender_code',
        'contact_email',
        'contact_phone',
        'destination_country',
        'currency',
        'total_amount',
        'processed_at',
        'is_booked',
        'booked_at',
        'booked_by',
        'shipped_at',
        'last_export_filename',
        'metadata',
        'dhl_shipment_id',
        'dhl_label_url',
        'dhl_label_pdf_base64',
        'dhl_pickup_reference',
        'dhl_product_id',
        'dhl_booking_payload',
        'dhl_booking_response',
        'dhl_booking_error',
        'dhl_booked_at',
    ];

    protected $casts = [
        'customer_number' => 'integer',
        'plenty_order_id' => 'integer',
        'total_amount' => 'float',
        'processed_at' => 'datetime',
        'is_booked' => 'bool',
        'booked_at' => 'datetime',
        'shipped_at' => 'datetime',
        'metadata' => 'array',
        'dhl_booking_payload' => 'array',
        'dhl_booking_response' => 'array',
        'dhl_booked_at' => 'datetime',
    ];

    /**
     * @return HasMany<ShipmentOrderItemModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentOrderItemModel::class, 'shipment_order_id');
    }

    /**
     * @return HasMany<ShipmentPackageModel, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackageModel::class, 'shipment_order_id');
    }

    /**
     * @return HasMany<ShipmentOrderShipmentModel, $this>
     */
    public function shipmentLinks(): HasMany
    {
        return $this->hasMany(ShipmentOrderShipmentModel::class, 'shipment_order_id');
    }

    /**
     * @return HasManyThrough<ShipmentModel, ShipmentOrderShipmentModel, $this>
     */
    public function shipments(): HasManyThrough
    {
        return $this->hasManyThrough(
            ShipmentModel::class,
            ShipmentOrderShipmentModel::class,
            'shipment_order_id',
            'id',
            'id',
            'shipment_id'
        );
    }

    protected static function newFactory(): ShipmentOrderModelFactory
    {
        return ShipmentOrderModelFactory::new();
    }
}
