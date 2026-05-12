<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment;

use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentFreightProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the t30 design-gap fix end-to-end: a ShipmentOrder can persist its
 * link to a FulfillmentFreightProfile and load it back through the repository.
 * This unblocks the per-profile DHL AccountNumber-Override resolved by
 * DhlSettingsResolver.
 */
final class FreightProfileLinkageTest extends TestCase
{
    use RefreshDatabase;

    public function test_freight_profile_id_is_persisted_and_loaded(): void
    {
        $profile = FulfillmentFreightProfileModel::factory()->create([
            'shipping_profile_id' => 4711,
        ]);

        $order = ShipmentOrderModel::factory()->create([
            'freight_profile_id' => $profile->shipping_profile_id,
        ]);

        $repository = app(ShipmentOrderRepository::class);

        $loaded = $repository->getById(
            \App\Domain\Shared\ValueObjects\Identifier::fromInt((int) $order->getKey()),
        );

        self::assertNotNull($loaded);
        self::assertSame(4711, $loaded->freightProfileId());
    }

    public function test_freight_profile_id_round_trips_through_save_and_load(): void
    {
        FulfillmentFreightProfileModel::factory()->create([
            'shipping_profile_id' => 808,
        ]);

        $order = ShipmentOrderModel::factory()->create([
            'freight_profile_id' => null,
        ]);

        $repository = app(ShipmentOrderRepository::class);
        $loaded = $repository->getById(
            \App\Domain\Shared\ValueObjects\Identifier::fromInt((int) $order->getKey()),
        );

        self::assertNotNull($loaded);
        self::assertNull($loaded->freightProfileId());

        $repository->save($loaded->withFreightProfileId(808));

        $reloaded = $repository->getById(
            \App\Domain\Shared\ValueObjects\Identifier::fromInt((int) $order->getKey()),
        );

        self::assertNotNull($reloaded);
        self::assertSame(808, $reloaded->freightProfileId());
        self::assertDatabaseHas('shipment_orders', [
            'id' => $order->getKey(),
            'freight_profile_id' => 808,
        ]);
    }

    public function test_clearing_freight_profile_id_persists_null(): void
    {
        FulfillmentFreightProfileModel::factory()->create([
            'shipping_profile_id' => 12,
        ]);

        $order = ShipmentOrderModel::factory()->create([
            'freight_profile_id' => 12,
        ]);

        $repository = app(ShipmentOrderRepository::class);
        $loaded = $repository->getById(
            \App\Domain\Shared\ValueObjects\Identifier::fromInt((int) $order->getKey()),
        );
        self::assertSame(12, $loaded?->freightProfileId());

        $repository->save($loaded->withFreightProfileId(null));

        self::assertDatabaseHas('shipment_orders', [
            'id' => $order->getKey(),
            'freight_profile_id' => null,
        ]);
    }
}
