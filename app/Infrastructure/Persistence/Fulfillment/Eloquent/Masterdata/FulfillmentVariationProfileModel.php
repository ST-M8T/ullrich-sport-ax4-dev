<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use Database\Factories\FulfillmentVariationProfileModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentVariationProfileModel extends Model
{
    /** @use HasFactory<\Database\Factories\FulfillmentVariationProfileModelFactory> */
    use HasFactory;

    protected $table = 'fulfillment_variation_profiles';

    protected $fillable = [
        'item_id',
        'variation_id',
        'variation_name',
        'default_state',
        'default_packaging_id',
        'default_weight_kg',
        'assembly_option_id',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('item_id')
            ->orderBy('variation_id');
    }

    /**
     * @return BelongsTo<FulfillmentPackagingProfileModel, $this>
     */
    public function defaultPackaging(): BelongsTo
    {
        return $this->belongsTo(FulfillmentPackagingProfileModel::class, 'default_packaging_id');
    }

    /**
     * @return BelongsTo<FulfillmentAssemblyOptionModel, $this>
     */
    public function assemblyOption(): BelongsTo
    {
        return $this->belongsTo(FulfillmentAssemblyOptionModel::class, 'assembly_option_id');
    }

    protected static function newFactory(): FulfillmentVariationProfileModelFactory
    {
        return FulfillmentVariationProfileModelFactory::new();
    }
}
