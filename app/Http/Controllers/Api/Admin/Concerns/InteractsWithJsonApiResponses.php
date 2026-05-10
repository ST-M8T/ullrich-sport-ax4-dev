<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

trait InteractsWithJsonApiResponses
{
    /**
     * @param  array{data?:mixed,errors?:mixed,meta?:mixed,links?:mixed}  $payload
     */
    private function jsonApiResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }

    /**
     * @param  MessageBag|array<string,array<int,string>>  $errors
     */
    private function jsonApiValidationErrors(MessageBag|array $errors): JsonResponse
    {
        $messages = $errors instanceof MessageBag ? $errors->getMessages() : $errors;

        $documents = [];
        foreach ($messages as $field => $fieldMessages) {
            foreach ((array) $fieldMessages as $message) {
                $documents[] = array_filter([
                    'status' => '422',
                    'title' => 'Unprocessable Entity',
                    'detail' => $message,
                    'source' => [
                        'pointer' => $this->attributePointer($field),
                    ],
                ], fn ($value) => $value !== '');
            }
        }

        return $this->jsonApiResponse(['errors' => $documents], 422);
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    private function jsonApiError(int $status, string $title, string $detail, ?string $code = null, array $meta = []): JsonResponse
    {
        $error = array_filter([
            'status' => (string) $status,
            'title' => $title,
            'detail' => $detail,
            'code' => $code,
            'meta' => $meta ?: null,
        ], fn ($value) => $value !== null);

        return $this->jsonApiResponse(['errors' => [$error]], $status);
    }

    private function attributePointer(string $field): string
    {
        $normalized = trim($field);
        if ($normalized === '') {
            return '/data';
        }

        return '/'.str_replace('.', '/', $normalized);
    }

    private function formatDate(?DateTimeInterface $value): ?string
    {
        return $value?->format(DateTimeInterface::ATOM);
    }
}
