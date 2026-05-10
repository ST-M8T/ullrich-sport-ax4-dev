<?php

namespace Tests\Feature\Monitoring;

use App\Infrastructure\Persistence\Monitoring\Eloquent\DomainEventModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainEventMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('support');
    }

    public function test_it_filters_domain_events_by_aggregate_and_time_range(): void
    {
        $now = Carbon::now();

        DomainEventModel::create([
            'id' => (string) Str::uuid(),
            'event_name' => 'shipment.created',
            'aggregate_type' => 'shipment',
            'aggregate_id' => 'SHIP-1',
            'payload' => ['status' => 'created'],
            'metadata' => [],
            'occurred_at' => $now,
            'created_at' => $now,
        ]);

        DomainEventModel::create([
            'id' => (string) Str::uuid(),
            'event_name' => 'shipment.updated',
            'aggregate_type' => 'shipment',
            'aggregate_id' => 'SHIP-2',
            'payload' => ['status' => 'updated'],
            'metadata' => [],
            'occurred_at' => $now->copy()->subDays(3),
            'created_at' => $now->copy()->subDays(3),
        ]);

        DomainEventModel::create([
            'id' => (string) Str::uuid(),
            'event_name' => 'order.created',
            'aggregate_type' => 'order',
            'aggregate_id' => 'ORD-1',
            'payload' => ['status' => 'new'],
            'metadata' => [],
            'occurred_at' => $now,
            'created_at' => $now,
        ]);

        $response = $this->get(route('monitoring-domain-events', [
            'aggregate_type' => 'shipment',
            'time_range' => '24h',
        ]));

        $response->assertOk();
        $response->assertSee('SHIP-1', false);
        $response->assertDontSee('SHIP-2', false);
        $response->assertDontSee('ORD-1', false);
    }

    public function test_it_renders_detail_modal_with_payload_and_metadata(): void
    {
        $event = DomainEventModel::create([
            'id' => $id = (string) Str::uuid(),
            'event_name' => 'shipment.dispatched',
            'aggregate_type' => 'shipment',
            'aggregate_id' => 'SHIP-99',
            'payload' => ['status' => 'dispatched'],
            'metadata' => ['source' => 'tracking'],
            'occurred_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        $response = $this->get(route('monitoring-domain-events'));

        $response->assertOk();
        $response->assertSee('template id="domain-event-'.$event->id.'"', false);
        $decoded = html_entity_decode($response->getContent());
        $this->assertStringContainsString('"status": "dispatched"', $decoded);
        $this->assertStringContainsString('"source": "tracking"', $decoded);
    }

    public function test_it_exports_domain_events_as_csv(): void
    {
        DomainEventModel::create([
            'id' => (string) Str::uuid(),
            'event_name' => 'domain.exported',
            'aggregate_type' => 'domain',
            'aggregate_id' => 'DOM-1',
            'payload' => ['value' => 1],
            'metadata' => [],
            'occurred_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        $response = $this->get(route('monitoring-domain-events', [
            'export' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('domain.exported', $csv);
        $this->assertStringContainsString('ID;Event', $csv);
        $this->assertStringContainsString('Aggregate Typ', $csv);
    }
}
