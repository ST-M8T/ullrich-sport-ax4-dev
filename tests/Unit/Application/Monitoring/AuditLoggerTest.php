<?php

namespace Tests\Unit\Application\Monitoring;

use App\Application\Monitoring\AuditLogger;
use App\Domain\Monitoring\AuditLogEntry;
use App\Domain\Monitoring\Contracts\AuditLogRepository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class AuditLoggerTest extends TestCase
{
    private AuditLogRepository&MockInterface $repository;

    private AuditLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(AuditLogRepository::class);
        $this->logger = new AuditLogger($this->repository);
    }

    public function test_log_appends_entry_with_context(): void
    {
        $this->repository
            ->shouldReceive('append')
            ->once()
            ->withArgs(function (AuditLogEntry $entry): bool {
                $this->assertSame('user', $entry->actorType());
                $this->assertSame('42', $entry->actorId());
                $this->assertSame('dispatch.list_closed', $entry->action());
                $this->assertSame(['dispatch_list_id' => 99], $entry->context());
                $this->assertSame('10.0.0.1', $entry->ipAddress());
                $this->assertSame('Mozilla/5.0', $entry->userAgent());

                return true;
            });

        $this->logger->log(
            action: 'dispatch.list_closed',
            actorType: 'user',
            actorId: '42',
            actorName: 'Dispatcher',
            context: ['dispatch_list_id' => 99],
            ipAddress: '10.0.0.1',
            userAgent: 'Mozilla/5.0'
        );
    }
}
