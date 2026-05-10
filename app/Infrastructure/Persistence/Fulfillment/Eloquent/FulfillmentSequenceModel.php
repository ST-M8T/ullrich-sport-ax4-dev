<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class FulfillmentSequenceModel extends Model
{
    public const ORDER_SEQUENCE = 'shipment_orders';

    public const SHIPMENT_SEQUENCE = 'shipments';

    public const SHIPMENT_EVENT_SEQUENCE = 'shipment_events';

    protected $table = 'fulfillment_sequences';

    protected $primaryKey = 'sequence_name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sequence_name',
        'next_id',
    ];

    protected $casts = [
        'next_id' => 'integer',
    ];

    /**
     * @param  callable():int|null  $resolveFloor
     * @param  callable(int):bool|null  $isTaken
     */
    public static function reserveNextId(string $sequence, ?callable $resolveFloor = null, ?callable $isTaken = null): int
    {
        return (int) DB::transaction(
            static function () use ($sequence, $resolveFloor, $isTaken): int {
                /** @var self|null $record */
                $record = self::query()
                    ->lockForUpdate()
                    ->find($sequence);

                if ($record === null) {
                    $floor = $resolveFloor !== null ? max(1, (int) $resolveFloor()) : null;

                    $record = new self([
                        'sequence_name' => $sequence,
                        'next_id' => $floor ?? 1,
                    ]);
                    $record->save();
                } elseif ($resolveFloor !== null) {
                    $floor = max(1, (int) $resolveFloor());

                    if ((int) $record->next_id < $floor) {
                        $record->next_id = $floor;
                        $record->save();
                    }
                }

                $next = (int) max(1, $record->next_id);

                if ($isTaken !== null) {
                    while ($isTaken($next)) {
                        $next++;
                    }
                }

                $record->next_id = $next + 1;
                $record->save();

                return $next;
            }
        );
    }

    public static function syncToAtLeast(string $sequence, int $minimumNext): void
    {
        $minimum = max(1, $minimumNext);

        DB::transaction(static function () use ($sequence, $minimum): void {
            /** @var self|null $record */
            $record = self::query()
                ->lockForUpdate()
                ->find($sequence);

            if ($record === null) {
                $record = new self([
                    'sequence_name' => $sequence,
                    'next_id' => $minimum,
                ]);
            } elseif ((int) $record->next_id < $minimum) {
                $record->next_id = $minimum;
            }

            $record->save();
        });
    }
}
