<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use Database\Factories\FulfillmentSenderProfileModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FulfillmentSenderProfileModel extends Model
{
    /** @use HasFactory<\Database\Factories\FulfillmentSenderProfileModelFactory> */
    use HasFactory;

    protected $table = 'fulfillment_sender_profiles';

    protected $fillable = [
        'sender_code',
        'display_name',
        'company_name',
        'contact_person',
        'email',
        'phone',
        'street_name',
        'street_number',
        'address_addition',
        'postal_code',
        'city',
        'country_iso2',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sender_code')
            ->orderBy('display_name');
    }

    protected static function newFactory(): FulfillmentSenderProfileModelFactory
    {
        return FulfillmentSenderProfileModelFactory::new();
    }
}
