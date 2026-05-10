<?php

namespace Tests\Unit\Application\Fulfillment\Integrations;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlServiceOptionCollection;
use PHPUnit\Framework\TestCase;

final class DhlServiceOptionCollectionTest extends TestCase
{
    public function test_from_array_parses_strings_and_arrays(): void
    {
        $collection = DhlServiceOptionCollection::fromArray([
            'INSURANCE',
            ['code' => 'LIFTGATE', 'parameters' => ['value' => true]],
            ['invalid' => 'ignored'],
        ]);

        $this->assertFalse($collection->isEmpty());
        $this->assertCount(2, $collection->all());
        $this->assertSame('INSURANCE', $collection->all()[0]->code());
        $this->assertSame(
            [
                ['code' => 'INSURANCE'],
                ['code' => 'LIFTGATE', 'parameters' => ['value' => true]],
            ],
            $collection->toArray(),
        );
    }

    public function test_is_empty_returns_true_when_no_valid_entries(): void
    {
        $collection = DhlServiceOptionCollection::fromArray([]);

        $this->assertTrue($collection->isEmpty());
    }
}
