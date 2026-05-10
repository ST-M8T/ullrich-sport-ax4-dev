<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders;

use Database\Factories\ShipmentOrderShipmentModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentOrderShipmentModel extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentOrderShipmentModelFactory> */
    use HasFactory;

    protected $table = 'shipment_order_shipments';

    protected $fillable = [
        'shipment_order_id',
        'shipment_id',
    ];

    /**
     * @return BelongsTo<ShipmentOrderModel, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrderModel::class, 'shipment_order_id');
    }

    /**
     * @return BelongsTo<ShipmentModel, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ShipmentModel::class, 'shipment_id');
    }

    protected static function newFactory(): ShipmentOrderShipmentModelFactory
    {
        return ShipmentOrderShipmentModelFactory::new();
    }
}
