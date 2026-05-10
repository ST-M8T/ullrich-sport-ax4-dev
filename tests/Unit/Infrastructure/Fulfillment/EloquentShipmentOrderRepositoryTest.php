<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Fulfillment;

use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\FulfillmentSequenceModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentEventModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentShipmentOrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_identity_advances_from_existing_orders(): void
    {
        $repository = app(ShipmentOrderRepository::class);

        ShipmentOrderModel::factory()->count(2)->create();

        $currentMax = (int) ShipmentOrderModel::query()->max('id');

        FulfillmentSequenceModel::query()
            ->where('sequence_name', FulfillmentSequenceModel::ORDER_SEQUENCE)
            ->update(['next_id' => 1]);

        $first = $repository->nextIdentity();
        $second = $repository->nextIdentity();

        self::assertSame($currentMax + 1, $first->toInt());
        self::assertSame($currentMax + 2, $second->toInt());
    }

    public function test_shipment_identity_advances_from_existing_records(): void
    {
        $repository = app(\App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository::class);

        ShipmentModel::factory()->count(3)->create([
            'shipping_profile_id' => null,
        ]);

        $currentMax = (int) ShipmentModel::query()->max('id');

        FulfillmentSequenceModel::query()
            ->where('sequence_name', FulfillmentSequenceModel::SHIPMENT_SEQUENCE)
            ->update(['next_id' => 1]);

        $first = $repository->nextIdentity();
        $second = $repository->nextIdentity();

        self::assertSame($currentMax + 1, $first->toInt());
        self::assertSame($currentMax + 2, $second->toInt());
    }

    public function test_next_event_identity_uses_global_sequence(): void
    {
        $repository = app(\App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository::class);

        $shipment = ShipmentModel::factory()->create([
            'shipping_profile_id' => null,
        ]);

        FulfillmentSequenceModel::query()
            ->where('sequence_name', FulfillmentSequenceModel::SHIPMENT_EVENT_SEQUENCE)
            ->update(['next_id' => 1]);

        $first = $repository->nextEventIdentity(Identifier::fromInt((int) $shipment->getKey()));
        ShipmentEventModel::factory()->create(['shipment_id' => $shipment->getKey(), 'id' => $first]);
        $second = $repository->nextEventIdentity(Identifier::fromInt((int) $shipment->getKey()));

        self::assertSame($first + 1, $second);
    }
}
