<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

interface DhlFreightGateway
{
    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function getTimetable(array $payload): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function listProducts(array $filters = []): array;

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function listAdditionalServices(string $productId, array $filters = []): array;

    /**
     * @param  array<string,mixed>  $services
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function validateAdditionalServices(string $productId, array $services, array $filters = []): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function bookShipment(array $payload): array;

    /**
     * @param  array<string,mixed>  $quoteModel
     * @return array<string,mixed>
     */
    public function getPriceQuote(array $quoteModel): array;

    /**
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function printLabel(string $shipmentId, array $options = []): array;

    /**
     * @param  array<string,mixed>  $shipment
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function printDocuments(array $shipment, array $options = []): array;

    /**
     * @param  array<int,array<string,mixed>>  $shipments
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function printMultipleDocuments(array $shipments, array $options = []): array;

    /**
     * @return array{status:int,duration_ms:float,body:mixed}
     */
    public function ping(): array;

    /**
     * Cancel a DHL shipment.
     *
     * Note: DHL Freight API has no standard cancel endpoint.
     * This method performs local cancellation marking and returns the result.
     * External API cancellation must be handled by the calling application service
     * if DHL introduces a cancel API.
     *
     * @param  string  $shipmentId  The DHL shipment ID to cancel
     * @param  string  $reason  The cancellation reason
     * @return array{success:bool,cancelled_at:string,confirmation_number:string|null,error:string|null}
     */
    public function cancelShipment(string $shipmentId, string $reason): array;
}
