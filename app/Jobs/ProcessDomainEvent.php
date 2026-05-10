<?php

namespace App\Jobs;

use App\Application\Monitoring\DomainEventProjector;
use App\Domain\Monitoring\DomainEventRecord;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final class ProcessDomainEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array{
     *     id: string,
     *     event_name: string,
     *     aggregate_type: string,
     *     aggregate_id: string,
     *     payload: array<string,mixed>,
     *     metadata: array<string,mixed>,
     *     occurred_at: string,
     *     created_at: string
     * }
     */
    private array $eventPayload;

    /**
     * @var array<int,int>
     */
    private array $backoffIntervals;

    public int $tries;

    public function __construct(DomainEventRecord $record, ?string $connection = null, ?string $queue = null)
    {
        $config = config('domain-events.queue');

        $this->connection = $connection ?? ($config['connection'] ?? config('queue.default'));
        $this->queue = $queue ?? ($config['name'] ?? 'domain-events');
        $this->tries = (int) ($config['tries'] ?? 5);
        $this->backoffIntervals = $this->normalizeBackoff($config['backoff'] ?? [60, 180, 600]);
        $this->afterCommit = true;

        $this->eventPayload = [
            'id' => $record->id()->toString(),
            'event_name' => $record->eventName(),
            'aggregate_type' => $record->aggregateType(),
            'aggregate_id' => $record->aggregateId(),
            'payload' => $record->payload(),
            'metadata' => $record->metadata(),
            'occurred_at' => $record->occurredAt()->format(DATE_ATOM),
            'created_at' => $record->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return $this->backoffIntervals;
    }

    public function handle(DomainEventProjector $projector): void
    {
        $projector->project($this->toRecord());
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'domain-event',
            'event:'.$this->eventPayload['event_name'],
            'aggregate:'.$this->eventPayload['aggregate_type'],
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Domain event processing failed', [
            'event_id' => $this->eventPayload['id'] ?? null,
            'event_name' => $this->eventPayload['event_name'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }

    private function toRecord(): DomainEventRecord
    {
        return DomainEventRecord::hydrate(
            $this->toUuid($this->eventPayload['id']),
            $this->eventPayload['event_name'],
            $this->eventPayload['aggregate_type'],
            $this->eventPayload['aggregate_id'],
            $this->eventPayload['payload'],
            $this->eventPayload['metadata'],
            $this->toImmutable($this->eventPayload['occurred_at']),
            $this->toImmutable($this->eventPayload['created_at']),
        );
    }

    private function toUuid(string $value): UuidInterface
    {
        return Uuid::fromString($value);
    }

    private function toImmutable(string $value): DateTimeImmutable
    {
        return CarbonImmutable::parse($value);
    }

    /**
     * @param  array<int,mixed>  $backoff
     * @return array<int,int>
     */
    private function normalizeBackoff(array $backoff): array
    {
        $normalized = [];

        foreach ($backoff as $value) {
            $value = is_numeric($value) ? (int) $value : 0;
            if ($value > 0) {
                $normalized[] = $value;
            }
        }

        if ($normalized === []) {
            $normalized = [60, 180, 600];
        }

        return $normalized;
    }
}
