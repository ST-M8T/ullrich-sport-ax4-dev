<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipments\Contracts;

use App\Domain\Fulfillment\Shipments\Shipment;
use App\Domain\Fulfillment\Shipments\ShipmentEvent;
use App\Domain\Fulfillment\Shipments\ShipmentPaginationResult;
use App\Domain\Shared\ValueObjects\Identifier;

interface ShipmentRepository
{
    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginate(int $page, int $perPage, array $filters = []): ShipmentPaginationResult;

    public function getByTrackingNumber(string $trackingNumber): ?Shipment;

    public function getById(Identifier $id): ?Shipment;

    public function nextIdentity(): Identifier;

    public function save(Shipment $shipment): void;

    public function appendEvent(Identifier $shipmentId, ShipmentEvent $event): void;

    public function nextEventIdentity(Identifier $shipmentId): int;
}
