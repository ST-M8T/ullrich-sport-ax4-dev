<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

/**
 * Integration Field Definition
 * Definiert ein einzelnes Konfigurationsfeld
 * DDD: Value Object - Immutable Field Definition
 */
final class IntegrationField
{
    /**
     * @param  array<string, string>|null  $options
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
        private readonly string $valueType,
        private readonly bool $required = false,
        private readonly bool $secret = false,
        private readonly ?string $placeholder = null,
        private readonly ?string $help = null,
        private readonly mixed $default = null,
        private readonly ?array $options = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function valueType(): string
    {
        return $this->valueType;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isSecret(): bool
    {
        return $this->secret;
    }

    public function placeholder(): ?string
    {
        return $this->placeholder;
    }

    public function help(): ?string
    {
        return $this->help;
    }

    public function default(): mixed
    {
        return $this->default;
    }

    /**
     * @return array<string, string>|null
     */
    public function options(): ?array
    {
        return $this->options;
    }

    /**
     * Konvertiert zu Array für UI
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'value_type' => $this->valueType,
            'required' => $this->required,
        ];

        if ($this->secret) {
            $data['value_type'] = 'secret';
            $data['skip_if_empty'] = true;
        }

        if ($this->placeholder !== null) {
            $data['placeholder'] = $this->placeholder;
        }

        if ($this->help !== null) {
            $data['help'] = $this->help;
        }

        if ($this->default !== null) {
            $data['default'] = $this->default;
        }

        if ($this->options !== null) {
            $data['options'] = $this->options;
        }

        return $data;
    }
}
