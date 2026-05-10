<?php

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use Database\Factories\FulfillmentSenderRuleModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentSenderRuleModel extends Model
{
    /** @use HasFactory<\Database\Factories\FulfillmentSenderRuleModelFactory> */
    use HasFactory;

    protected $table = 'fulfillment_sender_rules';

    protected $fillable = [
        'priority',
        'rule_type',
        'match_value',
        'target_sender_id',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('priority')
            ->orderBy('rule_type');
    }

    /**
     * @return BelongsTo<FulfillmentSenderProfileModel, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(FulfillmentSenderProfileModel::class, 'target_sender_id');
    }

    protected static function newFactory(): FulfillmentSenderRuleModelFactory
    {
        return FulfillmentSenderRuleModelFactory::new();
    }
}
