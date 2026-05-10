<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use Database\Factories\FulfillmentPackagingProfileModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FulfillmentPackagingProfileModel extends Model
{
    /** @use HasFactory<\Database\Factories\FulfillmentPackagingProfileModelFactory> */
    use HasFactory;

    protected $table = 'fulfillment_packaging_profiles';

    protected $fillable = [
        'package_name',
        'packaging_code',
        'length_mm',
        'width_mm',
        'height_mm',
        'truck_slot_units',
        'max_units_per_pallet_same_recipient',
        'max_units_per_pallet_mixed_recipient',
        'max_stackable_pallets_same_recipient',
        'max_stackable_pallets_mixed_recipient',
        'notes',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('package_name')
            ->orderBy('packaging_code');
    }

    protected static function newFactory(): FulfillmentPackagingProfileModelFactory
    {
        return FulfillmentPackagingProfileModelFactory::new();
    }
}
