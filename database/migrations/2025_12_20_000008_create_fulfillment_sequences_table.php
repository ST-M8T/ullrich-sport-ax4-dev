<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_sequences', function (Blueprint $table): void {
            $table->string('sequence_name')->primary();
            $table->unsignedBigInteger('next_id')->default(1);
            $table->timestamps();
        });

        $now = Carbon::now();

        $orderMax = Schema::hasTable('shipment_orders') ? (int) (DB::table('shipment_orders')->max('id') ?? 0) : 0;
        $shipmentMax = Schema::hasTable('shipments') ? (int) (DB::table('shipments')->max('id') ?? 0) : 0;
        $eventMax = Schema::hasTable('shipment_events') ? (int) (DB::table('shipment_events')->max('id') ?? 0) : 0;

        if ($orderMax > 0 || $shipmentMax > 0 || $eventMax > 0) {
            DB::table('fulfillment_sequences')->insert(array_filter([
                $this->sequenceRow('shipment_orders', $orderMax + 1, $now),
                $this->sequenceRow('shipments', $shipmentMax + 1, $now),
                $this->sequenceRow('shipment_events', $eventMax + 1, $now),
            ]));
        } else {
            DB::table('fulfillment_sequences')->insert([
                $this->sequenceRow('shipment_orders', 1, $now),
                $this->sequenceRow('shipments', 1, $now),
                $this->sequenceRow('shipment_events', 1, $now),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_sequences');
    }

    /**
     * @return array<string,mixed>
     */
    private function sequenceRow(string $name, int $nextId, Carbon $timestamp): array
    {
        return [
            'sequence_name' => $name,
            'next_id' => max(1, $nextId),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
};
