<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\DTOs;

use App\Domain\Fulfillment\Orders\ValueObjects\DhlServiceOption;

final class DhlServiceOptionCollection
{
    /**
     * @param  list<DhlServiceOption>  $options
     */
    private function __construct(
        private readonly array $options,
    ) {
        // Collection is immutable; options set during construction.
    }

    /**
     * @param  array<int,array<string,mixed>|string>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $options = [];
        foreach ($raw as $value) {
            if (is_string($value)) {
                $options[] = new DhlServiceOption($value);

                continue;
            }

            if (is_array($value) && isset($value['code'])) {
                $code = (string) $value['code'];
                $parameters = is_array($value['parameters'] ?? null) ? $value['parameters'] : null;
                $options[] = new DhlServiceOption($code, $parameters);
            }
        }

        return new self($options);
    }

    /**
     * @return list<DhlServiceOption>
     */
    public function all(): array
    {

        return $this->options;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function toArray(): array
    {

        return array_map(static fn (DhlServiceOption $option): array => $option->toArray(), $this->options);
    }

    public function isEmpty(): bool
    {

        return $this->options === [];
    }
}
