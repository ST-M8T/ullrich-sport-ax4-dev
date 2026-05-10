<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Domain\Monitoring\Contracts\DomainEventRepository;
use App\Domain\Monitoring\DomainEventRecord;
use App\Events\DomainEventRecorded;
use DateTimeImmutable;

class DomainEventService
{
    public function __construct(private readonly DomainEventRepository $events) {}

    /**
     * @psalm-param array<string,mixed> $payload
     * @psalm-param array<string,mixed> $metadata
     */
    public function record(
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        array $payload = [],
        array $metadata = []
    ): void {
        $id = $this->events->nextIdentity();
        $now = new DateTimeImmutable;

        $record = DomainEventRecord::hydrate(
            $id,
            $eventName,
            $aggregateType,
            $aggregateId,
            $payload,
            $metadata,
            $now,
            $now,
        );

        $this->events->append($record);

        event(new DomainEventRecorded($record));
    }
}
