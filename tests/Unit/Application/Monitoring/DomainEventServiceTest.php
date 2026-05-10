<?php

namespace Tests\Unit\Application\Monitoring;

use App\Application\Monitoring\DomainEventService;
use App\Domain\Monitoring\Contracts\DomainEventRepository;
use App\Domain\Monitoring\DomainEventRecord;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class DomainEventServiceTest extends TestCase
{
    private DomainEventRepository&MockInterface $repository;

    private DomainEventService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(DomainEventRepository::class);
        $this->service = new DomainEventService($this->repository);
    }

    public function test_record_appends_domain_event(): void
    {
        Bus::fake();

        $uuid = Uuid::uuid4();

        $this->repository
            ->shouldReceive('nextIdentity')
            ->once()
            ->andReturn($uuid);

        $this->repository
            ->shouldReceive('append')
            ->once()
            ->withArgs(function (DomainEventRecord $record) use ($uuid): bool {
                $this->assertTrue($record->id()->equals($uuid));
                $this->assertSame('dispatch.list.closed', $record->eventName());
                $this->assertSame('dispatch_list', $record->aggregateType());
                $this->assertSame('42', $record->aggregateId());
                $this->assertSame(['status' => 'closed'], $record->payload());
                $this->assertSame(['actor' => 'system'], $record->metadata());

                return true;
            });

        $this->service->record(
            'dispatch.list.closed',
            'dispatch_list',
            '42',
            ['status' => 'closed'],
            ['actor' => 'system']
        );
    }
}
