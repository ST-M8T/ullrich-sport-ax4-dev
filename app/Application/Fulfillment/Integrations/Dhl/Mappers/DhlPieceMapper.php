<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Mappers;

use App\Application\Fulfillment\Integrations\Dhl\Mappers\Exceptions\DhlPayloadAssemblyException;
use App\Domain\Fulfillment\Orders\ShipmentPackage;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPiece;

/**
 * Stateless piece mapper — converts a {@see ShipmentPackage} into a spec-conforming
 * DHL Freight {@see DhlPiece}.
 *
 * Conversions:
 *   - Dimensions are stored in millimetres on the domain VO and converted to
 *     centimeters (DHL spec) here.
 *   - Weight is already in kilograms on the domain.
 *   - Quantity becomes numberOfPieces (>= 1, fail-fast if zero).
 *
 * Marks-and-numbers are derived from the package reference (DHL-spec max 35).
 */
final class DhlPieceMapper
{
    private const MAX_MARKS = 35;

    private const MM_PER_CM = 10.0;

    public static function fromShipmentPackage(
        ShipmentPackage $package,
        DhlPackageType $defaultType,
    ): DhlPiece {
        $quantity = max(1, $package->quantity());

        $weightKg = $package->weightKg();
        if ($weightKg === null || $weightKg <= 0.0) {
            throw DhlPayloadAssemblyException::missing(
                'pieces[].weight',
                sprintf('package %s', (string) $package->id())
            );
        }

        $dimensions = $package->dimensions();
        $widthCm = $dimensions !== null ? self::mmToCm($dimensions->width()) : null;
        $heightCm = $dimensions !== null ? self::mmToCm($dimensions->height()) : null;
        $lengthCm = $dimensions !== null ? self::mmToCm($dimensions->length()) : null;

        return new DhlPiece(
            numberOfPieces: $quantity,
            packageType: $defaultType,
            weight: $weightKg,
            width: $widthCm,
            height: $heightCm,
            length: $lengthCm,
            marksAndNumbers: self::truncateOrNull($package->packageReference(), self::MAX_MARKS),
            goodsType: null,
        );
    }

    /**
     * Mappt ein UI-Form-Piece (siehe DhlBookingRequest::rules() pieces.*) auf
     * ein spec-konformes {@see DhlPiece}. Dimensionen werden hier in CM
     * erwartet (UI-Konvention), Gewicht in kg.
     *
     * Engineering-Handbuch §15: Diese Methode ueberbrueckt Application-DTO
     * (Form-Array) und Domain-VO; die fachlichen Invarianten erzwingt
     * weiterhin {@see DhlPiece} (Defense in Depth).
     *
     * @param  array<string,mixed>  $piece
     */
    public static function fromFormPiece(array $piece, DhlPackageType $defaultType): DhlPiece
    {
        $numberOfPieces = (int) ($piece['number_of_pieces'] ?? 0);
        if ($numberOfPieces < 1) {
            throw DhlPayloadAssemblyException::missing('pieces[].number_of_pieces', 'form piece');
        }

        if (! isset($piece['weight']) || ! is_numeric($piece['weight'])) {
            throw DhlPayloadAssemblyException::missing('pieces[].weight', 'form piece');
        }
        $weightKg = (float) $piece['weight'];

        $packageType = $defaultType;
        $rawPackageType = $piece['package_type'] ?? null;
        if (is_string($rawPackageType) && trim($rawPackageType) !== '') {
            $packageType = DhlPackageType::fromString(trim($rawPackageType));
        }

        return new DhlPiece(
            numberOfPieces: $numberOfPieces,
            packageType: $packageType,
            weight: $weightKg,
            width: self::numericOrNull($piece['width'] ?? null),
            height: self::numericOrNull($piece['height'] ?? null),
            length: self::numericOrNull($piece['length'] ?? null),
            marksAndNumbers: self::truncateOrNull(
                isset($piece['marks_and_numbers']) && is_string($piece['marks_and_numbers'])
                    ? $piece['marks_and_numbers']
                    : null,
                self::MAX_MARKS,
            ),
            goodsType: null,
        );
    }

    private static function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function mmToCm(int $mm): float
    {
        return round($mm / self::MM_PER_CM, 2);
    }

    private static function truncateOrNull(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_strlen($trimmed) > $max ? mb_substr($trimmed, 0, $max) : $trimmed;
    }
}
