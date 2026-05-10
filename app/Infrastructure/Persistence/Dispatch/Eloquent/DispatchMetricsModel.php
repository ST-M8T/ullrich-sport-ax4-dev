<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Dispatch\Eloquent;

use Database\Factories\DispatchMetricsModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispatchMetricsModel extends Model
{
    /** @use HasFactory<\Database\Factories\DispatchMetricsModelFactory> */
    use HasFactory;

    protected $table = 'dispatch_metrics';

    protected $primaryKey = 'dispatch_list_id';

    public $incrementing = false;

    protected $fillable = [
        'dispatch_list_id',
        'total_orders',
        'total_packages',
        'total_items',
        'total_truck_slots',
        'metrics',
    ];

    protected $casts = [
        'dispatch_list_id' => 'integer',
        'total_orders' => 'integer',
        'total_packages' => 'integer',
        'total_items' => 'integer',
        'total_truck_slots' => 'integer',
        'metrics' => 'array',
    ];

    protected static function newFactory(): DispatchMetricsModelFactory
    {
        return DispatchMetricsModelFactory::new();
    }
}
