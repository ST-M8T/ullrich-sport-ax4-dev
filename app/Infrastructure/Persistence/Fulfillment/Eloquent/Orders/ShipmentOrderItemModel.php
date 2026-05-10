<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders;

use Database\Factories\ShipmentOrderItemModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $shipment_order_id
 * @property int|null $item_id
 * @property int|null $variation_id
 */
class ShipmentOrderItemModel extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentOrderItemModelFactory> */
    use HasFactory;

    protected $table = 'shipment_order_items';

    protected $fillable = [
        'shipment_order_id',
        'item_id',
        'variation_id',
        'sku',
        'description',
        'quantity',
        'packaging_profile_id',
        'weight_kg',
        'is_assembly',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'packaging_profile_id' => 'integer',
        'weight_kg' => 'float',
        'is_assembly' => 'bool',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<ShipmentOrderModel, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrderModel::class, 'shipment_order_id');
    }

    protected static function newFactory(): ShipmentOrderItemModelFactory
    {
        return ShipmentOrderItemModelFactory::new();
    }
}
