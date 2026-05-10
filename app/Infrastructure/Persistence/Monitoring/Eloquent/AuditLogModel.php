<?php

namespace App\Infrastructure\Persistence\Monitoring\Eloquent;

use Database\Factories\AuditLogModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLogModel extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogModelFactory> */
    use HasFactory;

    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_name',
        'action',
        'context',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function newFactory(): AuditLogModelFactory
    {
        return AuditLogModelFactory::new();
    }
}
