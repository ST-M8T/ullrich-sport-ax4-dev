<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\Concerns\HasDhlErrorParsing;

final class DhlLabelResponseDto
{
    use HasDhlErrorParsing;

    /**
     * @return array<string,mixed>
     */
    protected function rawResponseArray(): array
    {
        return $this->response;
    }

    /**
     * @param  array<string,mixed>  $response
     */
    public function __construct(
        private readonly array $response,
    ) {
        // Response payload is preserved for later inspection.
    }

    public function labelUrl(): ?string
    {
        return $this->response['labelUrl'] ?? $this->response['url'] ?? $this->response['downloadUrl'] ?? null;
    }

    public function labelPdfBase64(): ?string
    {
        return $this->response['pdfBase64'] ?? $this->response['base64'] ?? $this->response['content'] ?? null;
    }

    public function format(): string
    {
        // Fallback `'PDF'` ist immer gesetzt — der Rückgabetyp ist daher non-nullable.
        return $this->response['format'] ?? 'PDF';
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
        return $this->labelUrl() !== null || $this->labelPdfBase64() !== null;
    }

}
