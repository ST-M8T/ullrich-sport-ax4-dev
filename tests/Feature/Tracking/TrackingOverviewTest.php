<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingAlertModel;
use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingJobModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrackingOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_can_be_filtered_by_type_and_status(): void
    {
        $this->authenticateOperationsUser();

        $targetScheduled = Carbon::now()->subHours(2);
        $targetJob = TrackingJobModel::query()->create([
            'job_type' => 'daily-sync',
            'status' => 'failed',
            'scheduled_at' => $targetScheduled,
            'created_at' => (clone $targetScheduled)->subMinutes(10),
            'updated_at' => $targetScheduled,
            'attempt' => 2,
            'last_error' => 'Runtime failed',
            'payload' => ['foo' => 'bar'],
            'result' => ['status' => 'failed'],
        ]);

        $completedScheduled = Carbon::now()->subHour();
        $completedJob = TrackingJobModel::query()->create([
            'job_type' => 'daily-sync',
            'status' => 'completed',
            'scheduled_at' => $completedScheduled,
            'created_at' => (clone $completedScheduled)->subMinutes(5),
            'updated_at' => $completedScheduled,
            'attempt' => 1,
            'payload' => [],
            'result' => ['status' => 'ok'],
        ]);

        $otherScheduled = Carbon::now()->subHour();
        $otherJob = TrackingJobModel::query()->create([
            'job_type' => 'other-job',
            'status' => 'failed',
            'scheduled_at' => $otherScheduled,
            'created_at' => (clone $otherScheduled)->subMinutes(5),
            'updated_at' => $otherScheduled,
            'attempt' => 3,
            'payload' => [],
            'result' => [],
        ]);

        $response = $this->get('/admin/tracking?tab=jobs&job_type=daily-sync&job_status=failed');

        $response->assertOk();
        $response->assertSee('daily-sync');
        $response->assertSee('Runtime failed');
        $response->assertSee('#'.$targetJob->id);
        $response->assertDontSee('#'.$otherJob->id);
        $response->assertDontSee('#'.$completedJob->id);
    }

    public function test_alerts_can_be_filtered_by_severity_and_ack_state(): void
    {
        $this->authenticateOperationsUser();

        TrackingAlertModel::query()->create([
            'alert_type' => 'quota',
            'severity' => 'critical',
            'message' => 'Quota reached',
            'channel' => 'mail',
            'metadata' => ['count' => 10],
        ]);

        TrackingAlertModel::query()->create([
            'alert_type' => 'quota',
            'severity' => 'critical',
            'message' => 'Quota acknowledged',
            'channel' => 'mail',
            'acknowledged_at' => Carbon::now(),
            'metadata' => ['count' => 5],
        ]);

        TrackingAlertModel::query()->create([
            'alert_type' => 'sync',
            'severity' => 'warning',
            'message' => 'Minor issue',
            'channel' => 'slack',
            'metadata' => [],
        ]);

        $response = $this->get('/admin/tracking?tab=alerts&alert_type=quota&alert_severity=critical&alert_is_acknowledged=0');

        $response->assertOk();
        $response->assertSee('Quota reached');
        $response->assertDontSee('Quota acknowledged');
        $response->assertDontSee('Minor issue');
    }

    public function test_job_detail_endpoint_returns_history(): void
    {
        $this->authenticateOperationsUser();

        $primaryScheduled = Carbon::now()->subHours(3);
        $primary = TrackingJobModel::query()->create([
            'job_type' => 'history-job',
            'status' => 'failed',
            'scheduled_at' => $primaryScheduled,
            'created_at' => (clone $primaryScheduled)->subMinutes(5),
            'updated_at' => Carbon::now()->subHours(2),
            'finished_at' => Carbon::now()->subHours(2),
            'attempt' => 2,
            'last_error' => 'Previous error',
            'payload' => ['batch' => 1],
            'result' => ['error' => 'Previous error'],
        ]);

        $historyScheduled = Carbon::now()->subHour();
        TrackingJobModel::query()->create([
            'job_type' => 'history-job',
            'status' => 'completed',
            'scheduled_at' => $historyScheduled,
            'created_at' => (clone $historyScheduled)->subMinutes(5),
            'updated_at' => Carbon::now()->subMinutes(10),
            'finished_at' => Carbon::now()->subMinutes(10),
            'attempt' => 1,
            'payload' => ['batch' => 2],
            'result' => ['status' => 'ok'],
        ]);

        $response = $this->getJson(route('tracking-jobs.show', ['job' => $primary->id]));

        $response->assertOk();
        $response->assertJsonPath('job.id', $primary->id);
        $response->assertJsonPath('job.job_type', 'history-job');
        $response->assertJsonPath('job.last_error', 'Previous error');
        $response->assertJsonPath('history.0.job_type', 'history-job');
        $response->assertJsonStructure([
            'job' => [
                'id',
                'job_type',
                'status',
                'attempt',
                'scheduled_at',
                'started_at',
                'finished_at',
                'created_at',
                'updated_at',
                'payload',
                'result',
            ],
            'history',
        ]);
    }

    public function test_retry_job_resets_status_and_result(): void
    {
        $this->authenticateOperationsUser();

        $retryScheduled = Carbon::now()->subHours(2);
        $job = TrackingJobModel::query()->create([
            'job_type' => 'retry-job',
            'status' => 'failed',
            'scheduled_at' => $retryScheduled,
            'created_at' => (clone $retryScheduled)->subMinutes(5),
            'updated_at' => Carbon::now()->subHour(),
            'started_at' => Carbon::now()->subHours(2),
            'finished_at' => Carbon::now()->subHour(),
            'attempt' => 3,
            'last_error' => 'Failure',
            'payload' => ['foo' => 'bar'],
            'result' => ['error' => 'Failure'],
        ]);

        $response = $this->postJson(route('tracking-jobs.retry', ['job' => $job->id]));

        $response->assertOk();
        $response->assertJsonPath('job.status', 'scheduled');
        $response->assertJsonPath('job.last_error', null);
        $response->assertJsonPath('job.result', []);

        $job->refresh();

        $this->assertSame('scheduled', $job->status);
        $this->assertNull($job->started_at);
        $this->assertNull($job->finished_at);
        $this->assertNull($job->last_error);
        $this->assertSame([], $job->result);
    }

    public function test_mark_job_failed_sets_error_message(): void
    {
        $this->authenticateOperationsUser();

        $manualScheduled = Carbon::now()->subHour();
        $job = TrackingJobModel::query()->create([
            'job_type' => 'manual-fail',
            'status' => 'running',
            'scheduled_at' => $manualScheduled,
            'created_at' => (clone $manualScheduled)->subMinutes(5),
            'updated_at' => Carbon::now()->subMinutes(45),
            'started_at' => Carbon::now()->subMinutes(45),
            'attempt' => 1,
            'payload' => ['foo' => 'bar'],
            'result' => [],
        ]);

        $response = $this->postJson(route('tracking-jobs.fail', ['job' => $job->id]), [
            'reason' => 'Manual intervention',
        ]);

        $response->assertOk();
        $response->assertJsonPath('job.status', 'failed');
        $response->assertJsonPath('job.last_error', 'Manual intervention');

        $job->refresh();

        $this->assertSame('failed', $job->status);
        $this->assertSame('Manual intervention', $job->last_error);
        $this->assertNotNull($job->finished_at);
    }

    public function test_acknowledge_alert_sets_timestamp(): void
    {
        $this->authenticateOperationsUser();

        $alert = TrackingAlertModel::query()->create([
            'alert_type' => 'ack-alert',
            'severity' => 'critical',
            'message' => 'Needs ack',
            'channel' => 'mail',
            'metadata' => ['foo' => 'bar'],
        ]);

        $response = $this->postJson(route('tracking-alerts.acknowledge', ['alert' => $alert->id]));

        $response->assertOk();
        $response->assertJsonPath('alert.is_acknowledged', true);
        $response->assertJsonPath('alert.acknowledged_at', fn ($value) => ! empty($value));

        $alert->refresh();
        $this->assertNotNull($alert->acknowledged_at);
    }

    private function authenticateOperationsUser(): void
    {
        $user = UserModel::query()->create([
            'username' => 'tracking-user',
            'display_name' => 'Tracking User',
            'email' => 'tracking@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'operations',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);
    }
}
