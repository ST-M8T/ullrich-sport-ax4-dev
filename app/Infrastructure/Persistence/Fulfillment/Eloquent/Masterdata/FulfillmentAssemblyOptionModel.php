<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use Database\Factories\FulfillmentAssemblyOptionModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentAssemblyOptionModel extends Model
{
    /** @use HasFactory<\Database\Factories\FulfillmentAssemblyOptionModelFactory> */
    use HasFactory;

    protected $table = 'fulfillment_assembly_options';

    protected $fillable = [
        'assembly_item_id',
        'assembly_packaging_id',
        'assembly_weight_kg',
        'description',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('assembly_item_id')
            ->orderBy('assembly_packaging_id');
    }

    /**
     * @return BelongsTo<FulfillmentPackagingProfileModel, $this>
     */
    public function packaging(): BelongsTo
    {
        return $this->belongsTo(FulfillmentPackagingProfileModel::class, 'assembly_packaging_id');
    }

    protected static function newFactory(): FulfillmentAssemblyOptionModelFactory
    {
        return FulfillmentAssemblyOptionModelFactory::new();
    }
}
