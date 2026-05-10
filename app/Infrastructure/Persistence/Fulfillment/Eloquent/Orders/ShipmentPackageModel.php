<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders;

use Database\Factories\ShipmentPackageModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $shipment_order_id
 */
class ShipmentPackageModel extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentPackageModelFactory> */
    use HasFactory;

    protected $table = 'shipment_packages';

    protected $fillable = [
        'shipment_order_id',
        'packaging_profile_id',
        'package_reference',
        'quantity',
        'weight_kg',
        'length_mm',
        'width_mm',
        'height_mm',
        'truck_slot_units',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'packaging_profile_id' => 'integer',
        'weight_kg' => 'float',
        'length_mm' => 'integer',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
        'truck_slot_units' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<ShipmentOrderModel, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrderModel::class, 'shipment_order_id');
    }

    protected static function newFactory(): ShipmentPackageModelFactory
    {
        return ShipmentPackageModelFactory::new();
    }
}
