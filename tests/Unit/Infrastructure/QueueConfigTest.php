<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use Tests\TestCase;

final class QueueConfigTest extends TestCase
{
    public function test_failover_queue_connection_prioritizes_redis(): void
    {
        $connections = config('queue.connections.failover.connections');

        $this->assertSame(['redis', 'database', 'deferred'], $connections);
    }
}
