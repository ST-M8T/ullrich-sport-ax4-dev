<?php

namespace App\Infrastructure\Persistence\Tracking\Eloquent;

use Database\Factories\TrackingAlertModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property \DateTimeInterface|null $sent_at
 * @property \DateTimeInterface|null $acknowledged_at
 */
class TrackingAlertModel extends Model
{
    /** @use HasFactory<\Database\Factories\TrackingAlertModelFactory> */
    use HasFactory;

    protected $table = 'tracking_alerts';

    protected $fillable = [
        'shipment_id',
        'alert_type',
        'severity',
        'channel',
        'message',
        'sent_at',
        'acknowledged_at',
        'metadata',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'shipment_id' => 'integer',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function newFactory(): TrackingAlertModelFactory
    {
        return TrackingAlertModelFactory::new();
    }
}
