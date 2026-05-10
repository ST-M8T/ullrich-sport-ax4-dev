<?php

namespace Tests\Feature\Monitoring;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use App\Infrastructure\Persistence\Monitoring\Eloquent\SystemJobModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SystemJobMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('support');
    }

    public function test_it_filters_system_jobs_by_status_and_time_range(): void
    {
        $now = Carbon::now();

        SystemJobModel::create([
            'job_name' => 'dispatch-recent',
            'job_type' => 'dispatch',
            'run_context' => 'cli',
            'status' => 'queued',
            'scheduled_at' => $now,
            'started_at' => $now,
            'finished_at' => null,
            'duration_ms' => null,
            'payload' => ['tracking_job_id' => 1],
            'result' => [],
            'error_message' => null,
        ]);

        $old = SystemJobModel::create([
            'job_name' => 'dispatch-old',
            'job_type' => 'dispatch',
            'run_context' => 'cli',
            'status' => 'queued',
            'scheduled_at' => $now->copy()->subDays(3),
            'started_at' => $now->copy()->subDays(3),
            'finished_at' => $now->copy()->subDays(3),
            'duration_ms' => 1000,
            'payload' => [],
            'result' => [],
            'error_message' => null,
        ]);

        $old->timestamps = false;
        $old->forceFill([
            'created_at' => $now->copy()->subDays(3),
            'updated_at' => $now->copy()->subDays(3),
        ])->save();
        $old->timestamps = true;

        SystemJobModel::create([
            'job_name' => 'dispatch-failed',
            'job_type' => 'dispatch',
            'run_context' => 'cli',
            'status' => 'failed',
            'scheduled_at' => $now,
            'started_at' => $now,
            'finished_at' => $now,
            'duration_ms' => 500,
            'payload' => [],
            'result' => [],
            'error_message' => 'Something went wrong',
        ]);

        $this->actingAs(UserModel::factory()->create(['role' => 'support']));

        $response = $this->get(route('monitoring-system-jobs', [
            'status' => 'queued',
            'time_range' => '24h',
        ]));

        $response->assertOk();
        $response->assertSee('dispatch-recent', false);
        $response->assertDontSee('dispatch-old', false);
        $response->assertDontSee('dispatch-failed', false);
    }

    public function test_it_renders_detail_modal_with_payload_and_result(): void
    {
        $job = SystemJobModel::create([
            'job_name' => 'payload-job',
            'job_type' => 'runtime',
            'run_context' => 'worker',
            'status' => 'succeeded',
            'scheduled_at' => Carbon::now(),
            'started_at' => Carbon::now(),
            'finished_at' => Carbon::now(),
            'duration_ms' => 250,
            'payload' => ['key' => 'value'],
            'result' => ['status' => 'ok'],
            'error_message' => null,
        ]);

        $this->actingAs(UserModel::factory()->create(['role' => 'support']));

        $response = $this->get(route('monitoring-system-jobs'));

        $response->assertOk();
        $response->assertSee('template id="system-job-'.$job->getKey().'"', false);
        $response->assertSee('&quot;key&quot;: &quot;value&quot;', false);
        $response->assertSee('&quot;status&quot;: &quot;ok&quot;', false);
    }

    public function test_it_exports_system_jobs_as_csv(): void
    {
        SystemJobModel::create([
            'job_name' => 'export-job',
            'job_type' => 'runtime',
            'run_context' => 'queue',
            'status' => 'running',
            'scheduled_at' => Carbon::now(),
            'started_at' => Carbon::now(),
            'finished_at' => null,
            'duration_ms' => null,
            'payload' => [],
            'result' => [],
            'error_message' => null,
        ]);

        $this->actingAs(UserModel::factory()->create(['role' => 'support']));

        $response = $this->get(route('monitoring-system-jobs', [
            'export' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('export-job', $csv);
        $this->assertStringContainsString('ID;Name;Typ;Status;Geplant', $csv);
    }
}
