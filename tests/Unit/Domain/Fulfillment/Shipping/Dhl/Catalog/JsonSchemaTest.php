<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\InvalidParameterException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\UnsupportedJsonSchemaFeatureException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema;
use PHPUnit\Framework\TestCase;

final class JsonSchemaTest extends TestCase
{
    public function test_simple_object_schema_validates(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'amount' => ['type' => 'number', 'minimum' => 0, 'maximum' => 10000],
                'currency' => ['type' => 'string', 'enum' => ['EUR', 'USD']],
            ],
            'required' => ['amount', 'currency'],
        ]);

        $schema->validate(['amount' => 123.45, 'currency' => 'EUR']);

        self::assertSame('object', $schema->toArray()['type']);
    }

    public function test_required_property_missing_throws(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => ['amount' => ['type' => 'number']],
            'required' => ['amount'],
        ]);

        $this->expectException(InvalidParameterException::class);
        $schema->validate([]);
    }

    public function test_minimum_violated_throws(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => ['n' => ['type' => 'integer', 'minimum' => 1]],
        ]);

        $this->expectException(InvalidParameterException::class);
        $schema->validate(['n' => 0]);
    }

    public function test_maximum_violated_throws(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => ['n' => ['type' => 'integer', 'maximum' => 100]],
        ]);

        $this->expectException(InvalidParameterException::class);
        $schema->validate(['n' => 101]);
    }

    public function test_enum_violation_throws(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => ['c' => ['type' => 'string', 'enum' => ['A', 'B']]],
        ]);

        $this->expectException(InvalidParameterException::class);
        $schema->validate(['c' => 'C']);
    }

    public function test_type_mismatch_throws(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => ['n' => ['type' => 'integer']],
        ]);

        $this->expectException(InvalidParameterException::class);
        $schema->validate(['n' => 'not an int']);
    }

    public function test_format_email_invalid_throws(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => ['e' => ['type' => 'string', 'format' => 'email']],
        ]);

        $this->expectException(InvalidParameterException::class);
        $schema->validate(['e' => 'not-an-email']);
    }

    public function test_one_of_is_unsupported(): void
    {
        $this->expectException(UnsupportedJsonSchemaFeatureException::class);
        JsonSchema::fromArray(['oneOf' => [['type' => 'string']]]);
    }

    public function test_any_of_is_unsupported(): void
    {
        $this->expectException(UnsupportedJsonSchemaFeatureException::class);
        JsonSchema::fromArray(['anyOf' => [['type' => 'string']]]);
    }

    public function test_all_of_is_unsupported(): void
    {
        $this->expectException(UnsupportedJsonSchemaFeatureException::class);
        JsonSchema::fromArray(['allOf' => [['type' => 'string']]]);
    }

    public function test_ref_is_unsupported(): void
    {
        $this->expectException(UnsupportedJsonSchemaFeatureException::class);
        JsonSchema::fromArray(['$ref' => '#/something']);
    }

    public function test_unknown_key_is_unsupported(): void
    {
        $this->expectException(UnsupportedJsonSchemaFeatureException::class);
        JsonSchema::fromArray(['fancyKey' => 'value']);
    }

    public function test_nested_property_path_in_exception(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'outer' => [
                    'type' => 'object',
                    'properties' => ['inner' => ['type' => 'integer']],
                    'required' => ['inner'],
                ],
            ],
            'required' => ['outer'],
        ]);

        try {
            $schema->validate(['outer' => []]);
            self::fail('Expected InvalidParameterException');
        } catch (InvalidParameterException $e) {
            self::assertStringContainsString('/outer/inner', $e->path);
        }
    }

    public function test_array_items_validated(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'array',
            'items' => ['type' => 'integer', 'minimum' => 0],
        ]);

        $schema->validate([1, 2, 3]);

        $this->expectException(InvalidParameterException::class);
        $schema->validate([1, -1, 3]);
    }

    public function test_additional_properties_false_rejects_extra(): void
    {
        $schema = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => ['a' => ['type' => 'integer']],
            'additionalProperties' => false,
        ]);

        $this->expectException(InvalidParameterException::class);
        $schema->validate(['a' => 1, 'b' => 2]);
    }

    public function test_equals_compares_schema_array(): void
    {
        $a = JsonSchema::fromArray(['type' => 'string']);
        $b = JsonSchema::fromArray(['type' => 'string']);
        $c = JsonSchema::fromArray(['type' => 'integer']);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
