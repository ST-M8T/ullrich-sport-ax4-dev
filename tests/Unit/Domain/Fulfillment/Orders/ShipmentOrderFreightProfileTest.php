<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Orders;

use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Covers the freightProfileId accessor + immutable mutator added in t30.
 * The field carries the FulfillmentFreightProfile linkage that
 * DhlSettingsResolver uses to apply the per-profile AccountNumber override.
 */
final class ShipmentOrderFreightProfileTest extends TestCase
{
    public function test_freight_profile_id_defaults_to_null_when_not_provided(): void
    {
        $order = $this->makeOrder();

        self::assertNull($order->freightProfileId());
    }

    public function test_freight_profile_id_is_returned_when_hydrated(): void
    {
        $order = $this->makeOrder(freightProfileId: 7);

        self::assertSame(7, $order->freightProfileId());
    }

    public function test_with_freight_profile_id_returns_new_instance_with_value(): void
    {
        $order = $this->makeOrder();

        $updated = $order->withFreightProfileId(42);

        self::assertNotSame($order, $updated);
        self::assertNull($order->freightProfileId(), 'original instance must remain unchanged (immutability)');
        self::assertSame(42, $updated->freightProfileId());
    }

    public function test_with_freight_profile_id_can_clear_to_null(): void
    {
        $order = $this->makeOrder(freightProfileId: 99);

        $cleared = $order->withFreightProfileId(null);

        self::assertNull($cleared->freightProfileId());
        self::assertSame(99, $order->freightProfileId(), 'original retained');
    }

    public function test_hydrate_normalises_zero_to_null(): void
    {
        // 0 is not a valid FK value (table starts at 1 / unsignedInteger PK).
        $order = $this->makeOrder(freightProfileId: 0);

        self::assertNull($order->freightProfileId());
    }

    public function test_with_freight_profile_id_rejects_non_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeOrder()->withFreightProfileId(-1);
    }

    public function test_with_freight_profile_id_preserves_other_state(): void
    {
        $order = $this->makeOrder(freightProfileId: null);

        $updated = $order->withFreightProfileId(5);

        self::assertSame($order->id()->toInt(), $updated->id()->toInt());
        self::assertSame($order->externalOrderId(), $updated->externalOrderId());
        self::assertSame($order->currency(), $updated->currency());
        self::assertSame($order->destinationCountry(), $updated->destinationCountry());
    }

    public function test_with_freight_profile_id_sets_updated_at(): void
    {
        $order = $this->makeOrder();
        $explicit = new DateTimeImmutable('2026-01-15 12:00:00');

        $updated = $order->withFreightProfileId(3, $explicit);

        self::assertSame($explicit->format(DATE_ATOM), $updated->updatedAt()->format(DATE_ATOM));
    }

    private function makeOrder(?int $freightProfileId = null): ShipmentOrder
    {
        return ShipmentOrder::hydrate(
            id: Identifier::fromInt(100),
            externalOrderId: 1001,
            customerNumber: null,
            plentyOrderId: null,
            orderType: null,
            senderProfileId: null,
            senderCode: null,
            contactEmail: null,
            contactPhone: null,
            destinationCountry: 'DE',
            currency: 'EUR',
            totalAmount: null,
            processedAt: null,
            isBooked: false,
            bookedAt: null,
            bookedBy: null,
            shippedAt: null,
            lastExportFilename: null,
            items: [],
            packages: [],
            trackingNumbers: [],
            metadata: [],
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            freightProfileId: $freightProfileId,
        );
    }
}
