<?php

declare(strict_types=1);

namespace Tests\Unit\Dispatch;

use App\Domain\Dispatch\DispatchMetrics;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DispatchMetricsTest extends TestCase
{
    public function test_hydrate_rejects_negative_totals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('total_orders must be greater than or equal to zero.');

        DispatchMetrics::hydrate(-1, 0, 0, 0, []);
    }
}
