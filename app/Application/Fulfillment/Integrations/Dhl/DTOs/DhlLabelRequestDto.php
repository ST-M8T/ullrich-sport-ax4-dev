<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

final class DhlLabelRequestDto
{
    /**
     * @param  array<string,mixed>  $options
     */
    public function __construct(
        private readonly string $shipmentId,
        private readonly array $options = [],
    ) {
        // Value object wrapper around the raw label payload.
    }

    public function shipmentId(): string
    {
        return $this->shipmentId;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'shipmentId' => $this->shipmentId,
        ];

        if (isset($this->options['format'])) {
            $payload['format'] = $this->options['format'];
        }

        if (isset($this->options['language'])) {
            $payload['language'] = $this->options['language'];
        }

        if (isset($this->options['include_waybill'])) {
            $payload['includeWaybill'] = (bool) $this->options['include_waybill'];
        }

        return $payload;
    }
}
