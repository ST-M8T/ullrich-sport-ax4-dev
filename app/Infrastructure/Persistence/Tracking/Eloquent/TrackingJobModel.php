<?php

namespace App\Infrastructure\Persistence\Tracking\Eloquent;

use Database\Factories\TrackingJobModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property \DateTimeInterface|null $updated_at
 * @property \DateTimeInterface|null $scheduled_at
 * @property \DateTimeInterface|null $started_at
 * @property \DateTimeInterface|null $finished_at
 */
class TrackingJobModel extends Model
{
    /** @use HasFactory<\Database\Factories\TrackingJobModelFactory> */
    use HasFactory;

    protected $table = 'tracking_jobs';

    protected $fillable = [
        'job_type',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'attempt',
        'last_error',
        'payload',
        'result',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'attempt' => 'integer',
        'payload' => 'array',
        'result' => 'array',
    ];

    protected static function newFactory(): TrackingJobModelFactory
    {
        return TrackingJobModelFactory::new();
    }
}
