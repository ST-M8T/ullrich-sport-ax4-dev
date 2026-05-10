<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders;

use Database\Factories\ShipmentEventModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentEventModel extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentEventModelFactory> */
    use HasFactory;

    protected $table = 'shipment_events';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'shipment_id',
        'event_code',
        'event_status',
        'event_description',
        'facility',
        'city',
        'country_iso2',
        'event_occurred_at',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'shipment_id' => 'integer',
        'event_occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'payload' => 'array',
    ];

    protected static function newFactory(): ShipmentEventModelFactory
    {
        return ShipmentEventModelFactory::new();
    }
}
