<?php

namespace Tests\Feature\DomainEvents;

use App\Application\Monitoring\DomainEventProjector;
use App\Application\Monitoring\SystemJobLifecycleService;
use App\Domain\Monitoring\DomainEventRecord;
use App\Jobs\DispatchDomainEventFollowUp;
use App\Jobs\ProcessDomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class ProcessDomainEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_event_projection_creates_read_model_and_follow_ups(): void
    {
        Bus::fake();

        $record = $this->makeRecord(
            'fulfillment.shipment.event_recorded',
            'shipment',
            '101',
            [
                'tracking_number' => 'TRACK-1001',
                'event_code' => 'DLV',
                'status' => 'DELIVERED',
                'occurred_at' => now()->subMinute()->format(DATE_ATOM),
            ],
            [
                'carrier_code' => 'dhl',
            ]
        );

        $this->runProcessJob($record);

        $this->assertDatabaseHas('reporting_shipment_events', [
            'event_id' => $record->id()->toString(),
            'tracking_number' => 'TRACK-1001',
            'status' => 'DELIVERED',
        ]);

        $this->assertTrue(DB::table('tracking_jobs')->where('job_type', 'monitor.shipment.event')->exists());

        Bus::assertDispatched(DispatchDomainEventFollowUp::class, function (DispatchDomainEventFollowUp $job) use ($record) {
            return $this->dispatchedEventData($job)['id'] === $record->id()->toString();
        });

        $this->runFollowUpJob();

        $this->assertDatabaseHas('system_jobs', [
            'job_name' => 'domain_event.follow_up',
            'run_context' => $record->id()->toString(),
        ]);
    }

    public function test_dispatch_event_projection_records_event(): void
    {
        Bus::fake();

        $record = $this->makeRecord(
            'dispatch.list.scan_captured',
            'dispatch_list',
            '42',
            [
                'barcode' => 'PKG-200',
                'captured_at' => now()->subMinutes(2)->format(DATE_ATOM),
            ]
        );

        $this->runProcessJob($record);

        $this->assertDatabaseHas('reporting_dispatch_events', [
            'event_id' => $record->id()->toString(),
            'barcode' => 'PKG-200',
        ]);

        Bus::assertDispatched(DispatchDomainEventFollowUp::class);
    }

    public function test_order_event_projection_records_synced_order(): void
    {
        Bus::fake();

        $record = $this->makeRecord(
            'fulfillment.shipment_order.synced',
            'shipment_order',
            '501',
            [
                'external_order_id' => 'PL-9001',
                'status' => 'BOOKED',
                'is_update' => true,
                'synced_at' => now()->format(DATE_ATOM),
            ],
            [
                'currency' => 'EUR',
                'total_amount' => 123.45,
            ]
        );

        $this->runProcessJob($record);

        $this->assertDatabaseHas('reporting_order_events', [
            'event_id' => $record->id()->toString(),
            'external_order_id' => 'PL-9001',
            'status' => 'BOOKED',
            'is_update' => true,
        ]);

        Bus::assertDispatched(DispatchDomainEventFollowUp::class);
    }

    public function test_notification_event_projection_records_sent_notification(): void
    {
        Bus::fake();

        $record = $this->makeRecord(
            'configuration.notification.sent',
            'notification',
            '702',
            [
                'channel' => 'mail',
                'notification_type' => 'shipment.update',
                'recipient' => 'ops@example.com',
                'sent_at' => now()->format(DATE_ATOM),
            ],
            [
                'template' => 'shipment-status',
            ]
        );

        $this->runProcessJob($record);

        $this->assertDatabaseHas('reporting_notification_events', [
            'event_id' => $record->id()->toString(),
            'notification_type' => 'shipment.update',
            'recipient' => 'ops@example.com',
        ]);

        Bus::assertDispatched(DispatchDomainEventFollowUp::class);
    }

    private function runProcessJob(DomainEventRecord $record): void
    {
        $job = new ProcessDomainEvent($record);
        $job->handle(app(DomainEventProjector::class));
    }

    private function runFollowUpJob(): void
    {
        $dispatched = Bus::dispatched(DispatchDomainEventFollowUp::class)->first();

        if ($dispatched === null) {
            return;
        }

        $dispatched->handle(app(SystemJobLifecycleService::class), app('monitoring.metrics'));
    }

    /**
     * @return array<string,mixed>
     */
    private function dispatchedEventData(DispatchDomainEventFollowUp $job): array
    {
        $property = new \ReflectionProperty(DispatchDomainEventFollowUp::class, 'domainEvent');
        $property->setAccessible(true);

        $value = $property->getValue($job);

        return is_array($value) ? $value : [];
    }

    private function makeRecord(string $event, string $aggregateType, string $aggregateId, array $payload, array $metadata = []): DomainEventRecord
    {
        $uuid = Uuid::uuid4();
        $occurredAt = now()->subSeconds(5)->toImmutable();
        $createdAt = now()->toImmutable();

        return DomainEventRecord::hydrate(
            $uuid,
            $event,
            $aggregateType,
            $aggregateId,
            $payload,
            $metadata,
            $occurredAt,
            $createdAt
        );
    }
}
