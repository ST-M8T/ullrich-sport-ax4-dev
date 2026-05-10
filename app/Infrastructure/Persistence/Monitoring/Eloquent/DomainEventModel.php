<?php

namespace App\Infrastructure\Persistence\Monitoring\Eloquent;

use Database\Factories\DomainEventModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DomainEventModel extends Model
{
    /** @use HasFactory<\Database\Factories\DomainEventModelFactory> */
    use HasFactory;

    protected $table = 'domain_events';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'event_name',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'metadata',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function newFactory(): DomainEventModelFactory
    {
        return DomainEventModelFactory::new();
    }
}
