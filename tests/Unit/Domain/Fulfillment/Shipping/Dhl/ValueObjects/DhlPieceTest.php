<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPiece;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlPieceTest extends TestCase
{
    public function test_minimal_piece_to_array_has_flat_dimensions_omitted(): void
    {
        $p = new DhlPiece(2, new DhlPackageType('PLT'), 25.5);
        $arr = $p->toArray();
        self::assertSame(2, $arr['numberOfPieces']);
        self::assertSame('PLT', $arr['packageType']);
        self::assertSame(25.5, $arr['weight']);
        self::assertArrayNotHasKey('width', $arr);
        self::assertArrayNotHasKey('height', $arr);
        self::assertArrayNotHasKey('length', $arr);
        self::assertArrayNotHasKey('marksAndNumbers', $arr);
        self::assertArrayNotHasKey('goodsType', $arr);
    }

    public function test_full_piece_emits_flat_dimensions_no_nested_object(): void
    {
        $p = new DhlPiece(
            numberOfPieces: 1,
            packageType: new DhlPackageType('COLI'),
            weight: 10.0,
            width: 80.0,
            height: 60.0,
            length: 120.0,
            marksAndNumbers: 'AX4-ORDER-7',
            goodsType: 'Sports equipment',
        );
        $arr = $p->toArray();
        self::assertSame(80.0, $arr['width']);
        self::assertSame(60.0, $arr['height']);
        self::assertSame(120.0, $arr['length']);
        self::assertArrayNotHasKey('dimensions', $arr);
        self::assertSame('AX4-ORDER-7', $arr['marksAndNumbers']);
        self::assertSame('Sports equipment', $arr['goodsType']);
    }

    public function test_zero_pieces_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlPiece(0, new DhlPackageType('PLT'), 1.0);
    }

    public function test_zero_weight_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlPiece(1, new DhlPackageType('PLT'), 0.0);
    }

    public function test_negative_dimension_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlPiece(1, new DhlPackageType('PLT'), 1.0, width: -5.0);
    }

    public function test_dimension_above_max_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlPiece(1, new DhlPackageType('PLT'), 1.0, height: 1000.0);
    }

    public function test_too_long_marks_and_numbers_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlPiece(1, new DhlPackageType('PLT'), 1.0, marksAndNumbers: str_repeat('x', 36));
    }
}
