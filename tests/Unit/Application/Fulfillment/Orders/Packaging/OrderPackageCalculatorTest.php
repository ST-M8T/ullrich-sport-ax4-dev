<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Orders\Packaging;

use App\Application\Fulfillment\Orders\Packaging\OrderPackageCalculator;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentVariationProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentOrderItem;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Mockery;
use Tests\TestCase;

final class OrderPackageCalculatorTest extends TestCase
{
    public function test_returns_empty_when_no_items_have_variation_profiles(): void
    {
        $variations = Mockery::mock(FulfillmentVariationProfileRepository::class);
        $packagings = Mockery::mock(FulfillmentPackagingProfileRepository::class);

        $variations->shouldReceive('findByItemId')->with(123)->andReturn([]);

        $order = $this->orderWithItems([
            $this->item(itemId: 123, quantity: 5),
        ]);

        $calculator = new OrderPackageCalculator($variations, $packagings);

        self::assertSame([], $calculator->calculate($order));
    }

    public function test_creates_one_package_when_quantity_fits_into_single_pallet(): void
    {
        $variation = $this->variation(packagingId: 7, weightKg: 2.5);
        $packaging = $this->packaging(id: 7, maxUnitsSame: 10, lengthMm: 1200, widthMm: 800, heightMm: 144);

        $variations = Mockery::mock(FulfillmentVariationProfileRepository::class);
        $packagings = Mockery::mock(FulfillmentPackagingProfileRepository::class);

        $variations->shouldReceive('findByItemId')->with(42)->andReturn([$variation]);
        $packagings->shouldReceive('getById')
            ->withArgs(fn (Identifier $id) => $id->toInt() === 7)
            ->andReturn($packaging);

        $order = $this->orderWithItems([
            $this->item(itemId: 42, quantity: 4, sku: 'SKU-X'),
        ]);

        $calculator = new OrderPackageCalculator($variations, $packagings);
        $packages = $calculator->calculate($order);

        self::assertCount(1, $packages);
        self::assertSame(4, $packages[0]->quantity());
        self::assertSame(10.0, $packages[0]->weightKg());
        self::assertSame('SKU-X', $packages[0]->packageReference());
        self::assertSame(1200, $packages[0]->lengthMillimetres());
    }

    public function test_splits_into_multiple_packages_when_quantity_exceeds_pallet_capacity(): void
    {
        $variation = $this->variation(packagingId: 3, weightKg: 1.0);
        $packaging = $this->packaging(id: 3, maxUnitsSame: 5);

        $variations = Mockery::mock(FulfillmentVariationProfileRepository::class);
        $packagings = Mockery::mock(FulfillmentPackagingProfileRepository::class);

        $variations->shouldReceive('findByItemId')->with(99)->andReturn([$variation]);
        $packagings->shouldReceive('getById')->andReturn($packaging);

        $order = $this->orderWithItems([
            $this->item(itemId: 99, quantity: 12),
        ]);

        $packages = (new OrderPackageCalculator($variations, $packagings))->calculate($order);

        self::assertCount(3, $packages);
        self::assertSame(5, $packages[0]->quantity());
        self::assertSame(5, $packages[1]->quantity());
        self::assertSame(2, $packages[2]->quantity());
    }

    public function test_skips_items_without_item_id(): void
    {
        $variations = Mockery::mock(FulfillmentVariationProfileRepository::class);
        $packagings = Mockery::mock(FulfillmentPackagingProfileRepository::class);

        $variations->shouldNotReceive('findByItemId');

        $order = $this->orderWithItems([
            $this->item(itemId: null, quantity: 3),
        ]);

        $packages = (new OrderPackageCalculator($variations, $packagings))->calculate($order);

        self::assertSame([], $packages);
    }

    /**
     * @param  array<int, ShipmentOrderItem>  $items
     */
    private function orderWithItems(array $items): ShipmentOrder
    {
        $now = new DateTimeImmutable;

        return ShipmentOrder::hydrate(
            Identifier::fromInt(1),
            12345,
            null, null, null, null, null, null, null, null, 'EUR', null,
            $now, false, null, null, null, null,
            $items,
            [],
            [],
            [],
            $now, $now,
        );
    }

    private function item(?int $itemId, int $quantity, ?string $sku = null): ShipmentOrderItem
    {
        return ShipmentOrderItem::hydrate(
            Identifier::placeholder(),
            Identifier::fromInt(1),
            $itemId,
            null,
            $sku,
            null,
            $quantity,
            null,
            null,
            false,
        );
    }

    private function variation(int $packagingId, ?float $weightKg): FulfillmentVariationProfile
    {
        return FulfillmentVariationProfile::hydrate(
            Identifier::fromInt(1),
            42,
            null,
            null,
            'kit',
            Identifier::fromInt($packagingId),
            $weightKg,
            null,
        );
    }

    private function packaging(int $id, int $maxUnitsSame, int $lengthMm = 1200, int $widthMm = 800, int $heightMm = 144): FulfillmentPackagingProfile
    {
        return FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt($id),
            'Pal '.$id,
            'PAL',
            $lengthMm,
            $widthMm,
            $heightMm,
            1,
            $maxUnitsSame,
            $maxUnitsSame,
            5,
            5,
            null,
        );
    }
}
