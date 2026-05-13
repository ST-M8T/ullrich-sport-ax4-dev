<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Fulfillment\Masterdata\Concerns;

use App\Http\Requests\Fulfillment\Masterdata\Concerns\WrapsRulesAsSometimes;
use PHPUnit\Framework\TestCase;

final class WrapsRulesAsSometimesTest extends TestCase
{
    public function test_passes_rules_through_unchanged_when_not_update(): void
    {
        $host = $this->makeHost();
        $input = [
            'name' => ['required', 'string', 'max:255'],
            'count' => ['nullable', 'integer'],
        ];

        $this->assertSame($input, $host->call($input, false));
    }

    public function test_prepends_sometimes_to_every_rule_list_on_update(): void
    {
        $host = $this->makeHost();
        $result = $host->call(
            [
                'name' => ['required', 'string', 'max:255'],
                'count' => ['nullable', 'integer'],
            ],
            true,
        );

        $this->assertSame(['sometimes', 'required', 'string', 'max:255'], $result['name']);
        $this->assertSame(['sometimes', 'nullable', 'integer'], $result['count']);
    }

    public function test_does_not_duplicate_sometimes_when_already_present(): void
    {
        $host = $this->makeHost();
        $result = $host->call(
            ['name' => ['sometimes', 'required', 'string']],
            true,
        );

        $this->assertSame(['sometimes', 'required', 'string'], $result['name']);
    }

    public function test_handles_empty_rule_lists(): void
    {
        $host = $this->makeHost();
        $result = $host->call(['name' => []], true);

        $this->assertSame(['sometimes'], $result['name']);
    }

    private function makeHost(): object
    {
        return new class
        {
            use WrapsRulesAsSometimes;

            /**
             * @param  array<string, array<int, mixed>>  $rules
             * @return array<string, array<int, mixed>>
             */
            public function call(array $rules, bool $isUpdate): array
            {
                return $this->applySometimes($rules, $isUpdate);
            }
        };
    }
}
