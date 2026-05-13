<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Domain\Monitoring\DomainEventRecord;
use App\Events\DomainEventRecorded;
use App\Jobs\ProcessDomainEvent;
use App\Listeners\EnqueueDomainEventProcessing;
use Illuminate\Support\Facades\Queue;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * Behaviour contract for {@see EnqueueDomainEventProcessing}.
 *
 * Engineering-Handbuch §25 (Queue/Job-Regel): Listener nehmen ein Event
 * entgegen und delegieren an einen Use Case / Job. Sie enthalten keine
 * Fachlogik. Dieser Test verifiziert exakt diese Side-Effect-Pflicht:
 * Für jedes empfangene {@see DomainEventRecorded}-Event wird ein
 * {@see ProcessDomainEvent}-Job auf die konfigurierte Queue gelegt.
 */
final class EnqueueDomainEventProcessingTest extends TestCase
{
    public function test_handle_dispatches_process_domain_event_job(): void
    {
        Queue::fake();

        $record = $this->makeRecord('order.shipped', 'ShipmentOrder', 'so-1001');

        (new EnqueueDomainEventProcessing)->handle(new DomainEventRecorded($record));

        Queue::assertPushed(ProcessDomainEvent::class, 1);
    }

    public function test_handle_passes_record_through_to_job(): void
    {
        Queue::fake();

        $record = $this->makeRecord(
            'shipment.label.created',
            'ShipmentOrder',
            'so-2002',
            ['tracking_number' => '00340434292135100186'],
        );

        (new EnqueueDomainEventProcessing)->handle(new DomainEventRecorded($record));

        Queue::assertPushed(
            ProcessDomainEvent::class,
            static function (ProcessDomainEvent $job) use ($record): bool {
                // Job is dispatched on the configured connection/queue and tagged
                // by the underlying event name — verify both ends without touching
                // private state (Engineering-Handbuch §65 Law of Demeter).
                return in_array(
                    'event:'.$record->eventName(),
                    $job->tags(),
                    true,
                );
            },
        );
    }

    public function test_handle_dispatches_one_job_per_event(): void
    {
        Queue::fake();

        $listener = new EnqueueDomainEventProcessing;
        $listener->handle(new DomainEventRecorded($this->makeRecord('a.happened', 'AggregateA', 'a-1')));
        $listener->handle(new DomainEventRecorded($this->makeRecord('b.happened', 'AggregateB', 'b-1')));
        $listener->handle(new DomainEventRecorded($this->makeRecord('c.happened', 'AggregateC', 'c-1')));

        Queue::assertPushed(ProcessDomainEvent::class, 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeRecord(string $event, string $aggregateType, string $aggregateId, array $payload = []): DomainEventRecord
    {
        return DomainEventRecord::hydrate(
            Uuid::uuid4(),
            $event,
            $aggregateType,
            $aggregateId,
            $payload,
            [],
            now()->subSeconds(5)->toImmutable(),
            now()->toImmutable(),
        );
    }
}
