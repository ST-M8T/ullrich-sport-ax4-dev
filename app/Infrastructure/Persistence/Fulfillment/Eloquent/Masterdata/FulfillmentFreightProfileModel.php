<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use Database\Factories\FulfillmentFreightProfileModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FulfillmentFreightProfileModel extends Model
{
    /** @use HasFactory<\Database\Factories\FulfillmentFreightProfileModelFactory> */
    use HasFactory;

    protected $table = 'fulfillment_freight_profiles';

    protected $primaryKey = 'shipping_profile_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'shipping_profile_id',
        'label',
        'dhl_product_id',
        'dhl_default_service_codes',
        'shipping_method_mapping',
        'account_number',
        'created_at',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('shipping_profile_id');
    }

    protected static function newFactory(): FulfillmentFreightProfileModelFactory
    {
        return FulfillmentFreightProfileModelFactory::new();
    }
}
