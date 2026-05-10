<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingAlertModel;
use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingJobModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrackingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_jobs_endpoint_returns_payload_and_result(): void
    {
        config(['services.api.key' => 'k3y']);

        TrackingJobModel::query()->create([
            'job_type' => 'dhl-sync',
            'status' => 'completed',
            'scheduled_at' => now()->subHour(),
            'started_at' => now()->subMinutes(50),
            'finished_at' => now()->subMinutes(40),
            'attempt' => 1,
            'last_error' => null,
            'payload' => ['cursor' => 42],
            'result' => ['synced' => 12],
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinutes(40),
        ]);

        $response = $this->getJson('/api/v1/tracking-jobs', [
            'X-API-Key' => 'k3y',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'job_type',
                    'status',
                    'attempt',
                    'payload',
                    'result',
                ],
            ],
        ]);

        $response->assertJsonFragment([
            'job_type' => 'dhl-sync',
            'status' => 'completed',
            'payload' => ['cursor' => 42],
            'result' => ['synced' => 12],
        ]);
    }

    public function test_tracking_alerts_endpoint_returns_status_flags(): void
    {
        config(['services.api.key' => 'k3y']);

        TrackingAlertModel::query()->create([
            'shipment_id' => null,
            'alert_type' => 'delivery.delay',
            'severity' => 'warning',
            'channel' => 'mail',
            'message' => 'Shipment delayed by carrier',
            'sent_at' => now()->subMinutes(20),
            'acknowledged_at' => null,
            'metadata' => ['tracking_number' => 'T1'],
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(20),
        ]);

        $response = $this->getJson('/api/v1/tracking-alerts', [
            'X-API-Key' => 'k3y',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'alert_type',
                    'severity',
                    'channel',
                    'message',
                    'is_sent',
                    'is_acknowledged',
                    'metadata',
                ],
            ],
        ]);

        $response->assertJsonFragment([
            'alert_type' => 'delivery.delay',
            'channel' => 'mail',
            'is_sent' => true,
            'is_acknowledged' => false,
            'metadata' => ['tracking_number' => 'T1'],
        ]);
    }
}
