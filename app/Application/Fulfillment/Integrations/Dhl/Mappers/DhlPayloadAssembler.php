<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Mappers;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\Mappers\Exceptions\DhlPayloadAssemblyException;
use App\Application\Fulfillment\Integrations\Dhl\Settings\DhlSettingsResolver;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlAccountNumber;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;

/**
 * Stateless DHL Freight payload assembler — single source of truth for
 * spec-conforming `bookShipment` and `priceQuote` request bodies.
 *
 * The assembler orchestrates:
 *   - DhlSettingsResolver  → AccountNumber (freight profile override → system default → fail-fast)
 *   - DhlPartyMapper       → consignor + consignee
 *   - DhlPieceMapper       → pieces (one per ShipmentPackage, with default package type fallback)
 *   - DhlReferenceMapper   → CNR / CNZ
 *
 * Every payload carries `_schema => 'v2'` so downstream consumers can detect the
 * spec generation deterministically (e.g. when migrating away from the legacy
 * `productId/sender/receiver/packages` shape).
 */
final class DhlPayloadAssembler
{
    public const SCHEMA_VERSION = 'v2';

    /**
     * @return array<string,mixed>
     */
    public static function buildBookingPayload(
        ShipmentOrder $order,
        FulfillmentSenderProfile $sender,
        DhlBookingOptions $options,
        DhlSettingsResolver $resolver,
        ?int $freightProfileId = null,
    ): array {
        $productCode = $options->productCode();
        if ($productCode === null) {
            throw DhlPayloadAssemblyException::missing('productCode', 'DhlBookingOptions');
        }

        $payerCode = $options->payerCode();
        if ($payerCode === null) {
            throw DhlPayloadAssemblyException::missing('payerCode', 'DhlBookingOptions');
        }

        $defaultPackageType = $options->defaultPackageType();
        if ($defaultPackageType === null) {
            throw DhlPayloadAssemblyException::missing('defaultPackageType', 'DhlBookingOptions');
        }

        $accountNumber = self::resolveAccountNumberVo($resolver, $freightProfileId);

        $parties = [
            DhlPartyMapper::consignorFromSenderProfile($sender, $accountNumber)->toArray(),
            DhlPartyMapper::consigneeFromOrder($order)->toArray(),
        ];

        [$pieces, $totalNumberOfPieces, $totalWeight] = self::buildPieces($order, $options, $defaultPackageType);

        $references = array_map(
            static fn ($reference) => $reference->toArray(),
            DhlReferenceMapper::fromOrder($order),
        );

        $payload = [
            '_schema' => self::SCHEMA_VERSION,
            'productCode' => $productCode->value,
            'payerCode' => $payerCode->value,
            'parties' => $parties,
            'pieces' => $pieces,
            'totalNumberOfPieces' => $totalNumberOfPieces,
            'totalWeight' => round($totalWeight, 3),
        ];

        if ($references !== []) {
            $payload['references'] = $references;
        }

        if ($options->pickupDate() !== null) {
            $payload['pickupDate'] = $options->pickupDate();
        }

        $services = $options->serviceOptions();
        if ($services->isEmpty() === false) {
            $payload['additionalServices'] = $services->toArray();
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    public static function buildPriceQuotePayload(
        ShipmentOrder $order,
        FulfillmentSenderProfile $sender,
        DhlBookingOptions $options,
        DhlSettingsResolver $resolver,
        ?int $freightProfileId = null,
    ): array {
        $productCode = $options->productCode();
        if ($productCode === null) {
            throw DhlPayloadAssemblyException::missing('productCode', 'DhlBookingOptions');
        }

        $defaultPackageType = $options->defaultPackageType();
        if ($defaultPackageType === null) {
            throw DhlPayloadAssemblyException::missing('defaultPackageType', 'DhlBookingOptions');
        }

        $accountNumber = self::resolveAccountNumberVo($resolver, $freightProfileId);

        $parties = [
            DhlPartyMapper::consignorFromSenderProfile($sender, $accountNumber)->toArray(),
            DhlPartyMapper::consigneeFromOrder($order)->toArray(),
        ];

        [$pieces, $totalNumberOfPieces, $totalWeight] = self::buildPieces($order, $options, $defaultPackageType);

        $payload = [
            '_schema' => self::SCHEMA_VERSION,
            'productCode' => $productCode->value,
            'parties' => $parties,
            'pieces' => $pieces,
            'totalNumberOfPieces' => $totalNumberOfPieces,
            'totalWeight' => round($totalWeight, 3),
        ];

        $payerCode = $options->payerCode();
        if ($payerCode !== null) {
            $payload['payerCode'] = $payerCode->value;
        }

        $services = $options->serviceOptions();
        if ($services->isEmpty() === false) {
            $payload['additionalServices'] = $services->toArray();
        }

        return $payload;
    }

    /**
     * Single source of truth fuer pieces[]-Aufbau (DRY): UI-Override hat
     * Vorrang vor den persistierten ShipmentPackages — Backward-Compat
     * bleibt erhalten, wenn kein Override gesetzt ist.
     *
     * @return array{0: array<int,array<string,mixed>>, 1: int, 2: float}
     */
    private static function buildPieces(
        ShipmentOrder $order,
        DhlBookingOptions $options,
        DhlPackageType $defaultPackageType,
    ): array {
        $pieces = [];
        $totalNumberOfPieces = 0;
        $totalWeight = 0.0;

        if ($options->hasPiecesOverride()) {
            foreach ((array) $options->piecesOverride() as $formPiece) {
                $piece = DhlPieceMapper::fromFormPiece($formPiece, $defaultPackageType);
                $pieces[] = $piece->toArray();
                $totalNumberOfPieces += $piece->numberOfPieces;
                $totalWeight += $piece->weight * $piece->numberOfPieces;
            }

            if ($pieces === []) {
                throw DhlPayloadAssemblyException::missing('pieces', 'override is empty');
            }

            return [$pieces, $totalNumberOfPieces, round($totalWeight, 3)];
        }

        $packages = $order->packages();
        if ($packages === []) {
            throw DhlPayloadAssemblyException::missing('pieces', 'order has no packages');
        }

        foreach ($packages as $package) {
            $piece = DhlPieceMapper::fromShipmentPackage($package, $defaultPackageType);
            $pieces[] = $piece->toArray();
            $totalNumberOfPieces += $piece->numberOfPieces;
            $totalWeight += $piece->weight * $piece->numberOfPieces;
        }

        return [$pieces, $totalNumberOfPieces, round($totalWeight, 3)];
    }

    private static function resolveAccountNumberVo(
        DhlSettingsResolver $resolver,
        ?int $freightProfileId,
    ): DhlAccountNumber {
        // Callers (DhlBookingRequestDto / DhlPriceQuoteRequestDto) thread
        // ShipmentOrder::freightProfileId() through. The resolver applies the
        // override (Profile.account_number > System Default) or fails fast if
        // neither source is configured.
        $accountString = $resolver->resolveAccountNumber($freightProfileId);

        return DhlAccountNumber::fromString($accountString);
    }
}
