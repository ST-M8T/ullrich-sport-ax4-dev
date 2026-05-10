<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Orders\ValueObjects;

final class DhlServiceOption
{
    private string $code;

    /**
     * @param  array<string, mixed>|null  $parameters
     */
    public function __construct(
        string $code,
        private readonly ?array $parameters = null,
    ) {
        $this->code = strtoupper(trim($code));
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function parameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'parameters' => $this->parameters,
        ], static fn ($value) => $value !== null && $value !== []);
    }
}
