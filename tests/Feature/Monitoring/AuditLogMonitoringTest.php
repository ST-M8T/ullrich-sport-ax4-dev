<?php

namespace Tests\Feature\Monitoring;

use App\Infrastructure\Persistence\Monitoring\Eloquent\AuditLogModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLogMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('support');
    }

    public function test_it_filters_audit_logs_by_username_and_time_range(): void
    {
        $now = Carbon::now();

        $now = Carbon::now();

        AuditLogModel::create([
            'actor_type' => 'user',
            'actor_id' => 'jane.doe',
            'actor_name' => 'Jane Doe',
            'action' => 'login',
            'context' => ['foo' => 'bar'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => $now,
        ]);

        AuditLogModel::create([
            'actor_type' => 'user',
            'actor_id' => 'john.doe',
            'actor_name' => 'John Doe',
            'action' => 'login',
            'context' => [],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => $now->copy()->subDays(2),
        ]);

        $this->assertSame(1, AuditLogModel::query()->where('actor_id', 'jane.doe')->count());

        $results = app(\App\Application\Monitoring\Queries\ListAuditLogs::class)([
            'username' => 'jane.doe',
            'from' => new \DateTimeImmutable('-25 hours'),
            'to' => new \DateTimeImmutable('+1 hour'),
        ], 10, 0);

        $items = is_array($results) ? $results : $results->items();
        $this->assertGreaterThan(
            0,
            count($items),
            'Expected at least one matching audit log'
        );

        $response = $this->get(route('monitoring-audit-logs', [
            'username' => 'jane.doe',
            'time_range' => '24h',
        ]));

        $response->assertOk();
        $response->assertSee('Jane Doe', false);
        $response->assertDontSee('John Doe', false);
    }

    public function test_it_renders_detail_modal_with_context(): void
    {
        $entry = AuditLogModel::create([
            'actor_type' => 'user',
            'actor_id' => 'modal.user',
            'actor_name' => 'Modal User',
            'action' => 'export',
            'context' => ['detail' => 'value'],
            'ip_address' => '192.168.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->get(route('monitoring-audit-logs'));

        $response->assertOk();
        $response->assertSee('template id="audit-log-'.$entry->getKey().'"', false);
        $response->assertSee('&quot;detail&quot;: &quot;value&quot;', false);
    }

    public function test_it_exports_filtered_audit_logs_as_csv(): void
    {
        AuditLogModel::create([
            'actor_type' => 'user',
            'actor_id' => 'export.user',
            'actor_name' => 'Export User',
            'action' => 'export',
            'context' => [],
            'ip_address' => '10.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->get(route('monitoring-audit-logs', [
            'export' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('ID;Zeitpunkt;Benutzer;Aktion;IP-Adresse', $csv);
        $this->assertStringContainsString('User Agent', $csv);
        $this->assertStringContainsString('Export User', $csv);
    }
}
