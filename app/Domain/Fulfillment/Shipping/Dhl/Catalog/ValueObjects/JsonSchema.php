<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\InvalidParameterException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\UnsupportedJsonSchemaFeatureException;

/**
 * Immutable VO wrapping a deliberately small subset of JSON Schema
 * (Draft 2020-12) used to describe DHL additional-service parameters.
 *
 * Supported keys (whitelist):
 *   type, properties, required, enum, minimum, maximum, format,
 *   items, additionalProperties.
 *
 * Unsupported constructs (oneOf, anyOf, allOf, $ref, if/then/else) cause an
 * UnsupportedJsonSchemaFeatureException at construction — we never silently
 * accept schemas we cannot enforce (Security §19, KISS).
 *
 * Supported `type` values: object, array, string, integer, number, boolean,
 * null. Supported `format` values: date, date-time, email, uri, uuid.
 */
final readonly class JsonSchema
{
    private const ALLOWED_KEYS = [
        'type', 'properties', 'required', 'enum',
        'minimum', 'maximum', 'format', 'items',
        'additionalProperties', 'description', 'title', 'default',
    ];

    private const UNSUPPORTED_KEYS = [
        'oneOf', 'anyOf', 'allOf', 'not', '$ref', 'if', 'then', 'else',
        'patternProperties', 'dependencies', 'dependentSchemas',
    ];

    private const ALLOWED_TYPES = [
        'object', 'array', 'string', 'integer', 'number', 'boolean', 'null',
    ];

    private const ALLOWED_FORMATS = [
        'date', 'date-time', 'email', 'uri', 'uuid',
    ];

    /**
     * @param  array<string,mixed>  $schema
     */
    private function __construct(public array $schema)
    {
    }

    /**
     * @param  array<string,mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        self::assertSupported($raw, '#');

        return new self($raw);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->schema;
    }

    public function equals(self $other): bool
    {
        return $this->schema === $other->schema;
    }

    /**
     * Validate a parameter set against this schema. Throws on the first
     * violation with a JSON-pointer path. Empty parameter arrays are accepted
     * for object schemas without `required` keys.
     *
     * @param  array<string,mixed>  $parameters
     */
    public function validate(array $parameters): void
    {
        $this->validateValue($parameters, $this->schema, '#');
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private static function assertSupported(array $schema, string $path): void
    {
        foreach (array_keys($schema) as $key) {
            if (in_array($key, self::UNSUPPORTED_KEYS, true)) {
                throw new UnsupportedJsonSchemaFeatureException($key);
            }
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                throw new UnsupportedJsonSchemaFeatureException($key);
            }
        }

        if (isset($schema['type']) && is_string($schema['type'])
            && ! in_array($schema['type'], self::ALLOWED_TYPES, true)) {
            throw new UnsupportedJsonSchemaFeatureException('type=' . $schema['type']);
        }

        if (isset($schema['format']) && is_string($schema['format'])
            && ! in_array($schema['format'], self::ALLOWED_FORMATS, true)) {
            throw new UnsupportedJsonSchemaFeatureException('format=' . $schema['format']);
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propName => $propSchema) {
                if (! is_array($propSchema)) {
                    throw new UnsupportedJsonSchemaFeatureException($path . '/properties/' . $propName);
                }
                self::assertSupported($propSchema, $path . '/properties/' . $propName);
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            self::assertSupported($schema['items'], $path . '/items');
        }
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function validateValue(mixed $value, array $schema, string $path): void
    {
        // type check first — drives subsequent branches
        if (isset($schema['type']) && is_string($schema['type'])) {
            $this->assertType($value, $schema['type'], $path);
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            if (! in_array($value, $schema['enum'], true)) {
                throw new InvalidParameterException($path, sprintf(
                    'value is not in enum (%s)',
                    implode(',', array_map(static fn ($v) => is_scalar($v) ? (string) $v : gettype($v), $schema['enum'])),
                ));
            }
        }

        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && (is_int($schema['minimum']) || is_float($schema['minimum']))
                && $value < $schema['minimum']) {
                throw new InvalidParameterException($path, sprintf('value < minimum (%s)', (string) $schema['minimum']));
            }
            if (isset($schema['maximum']) && (is_int($schema['maximum']) || is_float($schema['maximum']))
                && $value > $schema['maximum']) {
                throw new InvalidParameterException($path, sprintf('value > maximum (%s)', (string) $schema['maximum']));
            }
        }

        if (is_string($value) && isset($schema['format']) && is_string($schema['format'])) {
            $this->assertFormat($value, $schema['format'], $path);
        }

        if (is_array($value) && isset($schema['type']) && $schema['type'] === 'object') {
            $this->validateObject($value, $schema, $path);
        }

        if (is_array($value) && isset($schema['type']) && $schema['type'] === 'array'
            && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $idx => $item) {
                $this->validateValue($item, $schema['items'], $path . '/' . $idx);
            }
        }
    }

    /**
     * @param  array<string,mixed>  $value
     * @param  array<string,mixed>  $schema
     */
    private function validateObject(array $value, array $schema, string $path): void
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($required as $requiredKey) {
            if (! is_string($requiredKey)) {
                continue;
            }
            if (! array_key_exists($requiredKey, $value)) {
                throw new InvalidParameterException(
                    $path . '/' . $requiredKey,
                    'required property missing',
                );
            }
        }

        foreach ($value as $propName => $propValue) {
            if (! is_string($propName)) {
                continue;
            }
            if (isset($properties[$propName]) && is_array($properties[$propName])) {
                $this->validateValue($propValue, $properties[$propName], $path . '/' . $propName);

                continue;
            }
            // Unknown property — respect additionalProperties: false
            if (array_key_exists('additionalProperties', $schema)
                && $schema['additionalProperties'] === false) {
                throw new InvalidParameterException(
                    $path . '/' . $propName,
                    'additional property not allowed',
                );
            }
        }
    }

    private function assertType(mixed $value, string $type, string $path): void
    {
        $matches = match ($type) {
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'array' => is_array($value) && ($value === [] || array_is_list($value)),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => false,
        };

        if (! $matches) {
            throw new InvalidParameterException(
                $path,
                sprintf('expected type "%s", got "%s"', $type, get_debug_type($value)),
            );
        }
    }

    private function assertFormat(string $value, string $format, string $path): void
    {
        $valid = match ($format) {
            'date' => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value),
            'date-time' => strtotime($value) !== false,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uri' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'uuid' => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value),
            default => true,
        };

        if (! $valid) {
            throw new InvalidParameterException($path, sprintf('does not match format "%s"', $format));
        }
    }
}
