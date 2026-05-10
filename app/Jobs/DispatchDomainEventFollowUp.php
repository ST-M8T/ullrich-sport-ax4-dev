<?php

namespace App\Jobs;

use App\Application\Monitoring\Metrics\MetricsRecorder;
use App\Application\Monitoring\SystemJobLifecycleService;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchDomainEventFollowUp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<string,mixed>
     */
    private array $domainEvent;

    /**
     * @var array<int,int>
     */
    private array $backoffIntervals;

    public int $tries;

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $metadata
     */
    public function __construct(
        string $eventId,
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        array $metadata,
        ?string $connection = null,
        ?string $queue = null
    ) {
        $config = config('domain-events.follow_up_queue');

        $this->connection = $connection ?? ($config['connection'] ?? config('queue.default'));
        $this->queue = $queue ?? ($config['name'] ?? 'monitoring');
        $this->tries = (int) ($config['tries'] ?? 3);
        $this->backoffIntervals = $this->normalizeBackoff($config['backoff'] ?? [120, 300, 600]);
        $this->afterCommit = true;

        $this->domainEvent = [
            'id' => $eventId,
            'name' => $eventName,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return $this->backoffIntervals;
    }

    public function handle(SystemJobLifecycleService $jobs, MetricsRecorder $metrics): void
    {
        $scheduledAt = new DateTimeImmutable;

        $entry = $jobs->start(
            'domain_event.follow_up',
            $this->domainEvent['aggregate_type'],
            $this->domainEvent['id'],
            [
                'event_id' => $this->domainEvent['id'],
                'event_name' => $this->domainEvent['name'],
                'aggregate_id' => $this->domainEvent['aggregate_id'],
                'payload' => $this->domainEvent['payload'],
                'metadata' => $this->domainEvent['metadata'],
            ],
            $scheduledAt
        );

        $jobs->finish($entry, 'completed', [
            'event_id' => $this->domainEvent['id'],
            'event_name' => $this->domainEvent['name'],
        ]);

        $metrics->increment('domain_events.follow_up', 1, [
            'event' => $this->domainEvent['name'],
            'aggregate_type' => $this->domainEvent['aggregate_type'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'domain-event-follow-up',
            'event:'.$this->domainEvent['name'],
            'aggregate:'.$this->domainEvent['aggregate_type'],
        ];
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
            $normalized = [120, 300, 600];
        }

        return $normalized;
    }
}
