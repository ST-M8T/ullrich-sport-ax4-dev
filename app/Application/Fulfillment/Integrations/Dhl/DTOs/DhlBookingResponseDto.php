<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

final class DhlBookingResponseDto
{
    /**
     * @param  array<string,mixed>  $response
     */
    public function __construct(
        private readonly array $response,
    ) {
        // Responses are stored as-is for downstream mapping.
    }

    public function shipmentId(): ?string
    {
        return $this->response['shipmentId'] ?? $this->response['id'] ?? null;
    }

    /**
     * @return array<int,string>
     */
    public function trackingNumbers(): array
    {
        $tracking = $this->response['trackingNumbers'] ?? $this->response['tracking_number'] ?? [];
        if (is_string($tracking)) {
            return [$tracking];
        }
        if (is_array($tracking)) {
            return array_values(array_filter(array_map('strval', $tracking)));
        }

        return [];
    }

    public function status(): ?string
    {
        return $this->response['status'] ?? $this->response['bookingStatus'] ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    public function rawResponse(): array
    {
        return $this->response;
    }

    public function isSuccess(): bool
    {
        $status = $this->status();
        if ($status === null) {
            return $this->shipmentId() !== null;
        }

        return in_array(strtolower($status), ['success', 'booked', 'confirmed', 'completed'], true);
    }

    public function errorMessage(): ?string
    {
        if ($this->isSuccess()) {
            return null;
        }

        return $this->response['error'] ?? $this->response['message'] ?? $this->response['errorMessage'] ?? null;
    }
}
