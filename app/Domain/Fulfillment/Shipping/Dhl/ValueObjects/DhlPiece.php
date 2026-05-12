<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * DHL Freight piece (spec: pieces[]).
 *
 * Spec constraints:
 *   - numberOfPieces      : >= 1
 *   - packageType         : DhlPackageType
 *   - weight              : kilograms, > 0,   <= 99999
 *   - width / height /
 *     length              : centimeters, > 0, <= 999     (each optional)
 *   - marksAndNumbers     : 0..35
 *   - goodsType           : 0..35
 *
 * Dimensions are FLAT scalars on the spec — there is no nested `dimensions`
 * object.
 */
final readonly class DhlPiece
{
    private const MAX_WEIGHT_KG = 99999.0;

    private const MAX_DIMENSION_CM = 999.0;

    private const MAX_TEXT = 35;

    public function __construct(
        public int $numberOfPieces,
        public DhlPackageType $packageType,
        public float $weight,
        public ?float $width = null,
        public ?float $height = null,
        public ?float $length = null,
        public ?string $marksAndNumbers = null,
        public ?string $goodsType = null,
    ) {
        if ($numberOfPieces < 1) {
            throw DhlValueObjectException::invalid(
                'piece.numberOfPieces',
                'must be >= 1',
                (string) $numberOfPieces,
            );
        }

        $this->assertPositiveBounded($weight, self::MAX_WEIGHT_KG, 'piece.weight');

        if ($width !== null) {
            $this->assertPositiveBounded($width, self::MAX_DIMENSION_CM, 'piece.width');
        }
        if ($height !== null) {
            $this->assertPositiveBounded($height, self::MAX_DIMENSION_CM, 'piece.height');
        }
        if ($length !== null) {
            $this->assertPositiveBounded($length, self::MAX_DIMENSION_CM, 'piece.length');
        }
        if ($marksAndNumbers !== null) {
            $this->assertMaxLength($marksAndNumbers, self::MAX_TEXT, 'piece.marksAndNumbers');
        }
        if ($goodsType !== null) {
            $this->assertMaxLength($goodsType, self::MAX_TEXT, 'piece.goodsType');
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $out = [
            'numberOfPieces' => $this->numberOfPieces,
            'packageType' => $this->packageType->code,
            'weight' => $this->weight,
        ];
        if ($this->width !== null) {
            $out['width'] = $this->width;
        }
        if ($this->height !== null) {
            $out['height'] = $this->height;
        }
        if ($this->length !== null) {
            $out['length'] = $this->length;
        }
        if ($this->marksAndNumbers !== null) {
            $out['marksAndNumbers'] = $this->marksAndNumbers;
        }
        if ($this->goodsType !== null) {
            $out['goodsType'] = $this->goodsType;
        }

        return $out;
    }

    private function assertPositiveBounded(float $value, float $max, string $field): void
    {
        if ($value <= 0.0) {
            throw DhlValueObjectException::invalid($field, 'must be > 0', (string) $value);
        }
        if ($value > $max) {
            throw DhlValueObjectException::invalid($field, sprintf('must be <= %s', (string) $max), (string) $value);
        }
    }

    private function assertMaxLength(string $value, int $max, string $field): void
    {
        if (mb_strlen($value) > $max) {
            throw DhlValueObjectException::invalid($field, sprintf('max length %d', $max), $value);
        }
    }
}
