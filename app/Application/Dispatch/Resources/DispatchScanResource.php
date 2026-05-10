<?php

declare(strict_types=1);

namespace App\Application\Dispatch\Resources;

use App\Domain\Dispatch\DispatchScan;

final class DispatchScanResource
{
    /**
     * @param  array<string, mixed>|null  $orderDetails
     */
    private function __construct(
        private readonly DispatchScan $scan,
        private readonly ?string $capturedByUsername = null,
        private readonly ?array $orderDetails = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $orderDetails
     */
    public static function fromDomain(
        DispatchScan $scan,
        ?string $capturedByUsername = null,
        ?array $orderDetails = null
    ): self {
        return new self($scan, $capturedByUsername, $orderDetails);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->scan->id()->toInt(),
            'barcode' => $this->scan->barcode(),
            'shipment_order_id' => $this->scan->shipmentOrderId()?->toInt(),
            'captured_by_user_id' => $this->scan->capturedByUserId()?->toInt(),
            'captured_by_username' => $this->capturedByUsername,
            'captured_at' => $this->scan->capturedAt()?->format(DATE_ATOM),
            'metadata' => $this->scan->metadata(),
            'created_at' => $this->scan->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->scan->updatedAt()->format(DATE_ATOM),
        ];

        if ($this->orderDetails !== null) {
            $data['order'] = $this->orderDetails;
        }

        return $data;
    }
}
