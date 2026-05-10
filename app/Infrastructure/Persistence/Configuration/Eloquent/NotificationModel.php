<?php

namespace App\Infrastructure\Persistence\Configuration\Eloquent;

use Database\Factories\NotificationModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property \DateTimeInterface|null $scheduled_at
 * @property \DateTimeInterface|null $sent_at
 */
class NotificationModel extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationModelFactory> */
    use HasFactory;

    protected $table = 'notifications_queue';

    protected $fillable = [
        'notification_type',
        'channel',
        'payload',
        'status',
        'scheduled_at',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected static function newFactory(): NotificationModelFactory
    {
        return NotificationModelFactory::new();
    }
}
