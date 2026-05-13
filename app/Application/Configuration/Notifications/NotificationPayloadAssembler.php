<?php

declare(strict_types=1);

namespace App\Application\Configuration\Notifications;

use JsonException;

/**
 * Decodes the raw JSON payload coming from the manual-notification form
 * and enriches it with conveniently-extracted "template" / "to" hints.
 */
final class NotificationPayloadAssembler
{
    /**
     * @return array<string,mixed>
     *
     * @throws JsonException                if $rawJson is not valid JSON
     * @throws \InvalidArgumentException    if decoded value is not a JSON object
     */
    public function assemble(?string $rawJson, ?string $templateKey, ?string $recipient): array
    {
        $payload = $this->decode($rawJson);

        if (! empty($templateKey) && ! array_key_exists('template', $payload)) {
            $payload['template'] = $templateKey;
        }
        if (! empty($recipient) && ! array_key_exists('to', $payload)) {
            $payload['to'] = $recipient;
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private function decode(?string $rawJson): array
    {
        if (empty($rawJson)) {
            return [];
        }

        $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Payload muss ein JSON-Objekt sein.');
        }

        return $decoded;
    }
}
