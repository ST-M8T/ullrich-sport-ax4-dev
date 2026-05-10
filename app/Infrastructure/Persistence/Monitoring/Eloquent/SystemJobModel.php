<?php

namespace App\Infrastructure\Persistence\Monitoring\Eloquent;

use Database\Factories\SystemJobModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemJobModel extends Model
{
    /** @use HasFactory<\Database\Factories\SystemJobModelFactory> */
    use HasFactory;

    protected $table = 'system_jobs';

    protected $fillable = [
        'job_name',
        'job_type',
        'run_context',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'duration_ms',
        'payload',
        'result',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'payload' => 'array',
        'result' => 'array',
    ];

    protected static function newFactory(): SystemJobModelFactory
    {
        return SystemJobModelFactory::new();
    }
}
