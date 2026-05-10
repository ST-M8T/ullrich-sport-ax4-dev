<?php

namespace Tests\Feature\Api\Admin;

use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use App\Infrastructure\Persistence\Monitoring\Eloquent\SystemJobModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class AdminSystemStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        config(['services.admin_api.token' => 'secret-token']);

        $response = $this->getJson('/api/admin/system-status');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonPath('errors.0.status', '401');
    }

    public function test_returns_system_status_summary(): void
    {
        config(['services.admin_api.token' => 'secret-token']);
        Carbon::setTestNow('2024-12-01 10:00:00');

        SystemSettingModel::query()->create([
            'setting_key' => 'tracking_api_key',
            'setting_value' => 'secret-key',
            'value_type' => 'string',
            'updated_at' => now(),
        ]);

        SystemSettingModel::query()->create([
            'setting_key' => 'tracking_api_secret',
            'setting_value' => null,
            'value_type' => 'string',
            'updated_by_user_id' => null,
            'updated_at' => now(),
        ]);

        SystemJobModel::query()->create([
            'job_name' => 'tracking:sync',
            'status' => 'completed',
            'scheduled_at' => now()->subMinutes(15),
            'started_at' => now()->subMinutes(14),
            'finished_at' => now()->subMinutes(12),
            'duration_ms' => 1200,
            'payload' => ['batch' => 10],
            'result' => ['processed' => 10],
            'error_message' => null,
        ]);

        SystemJobModel::query()->create([
            'job_name' => 'tracking:sync',
            'status' => 'pending',
            'scheduled_at' => now()->addMinutes(5),
            'payload' => ['batch' => 5],
            'result' => null,
            'error_message' => null,
        ]);

        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir.DIRECTORY_SEPARATOR.'laravel.log';
        File::put($logFile, "[2024-12-01 10:00:00] local.INFO: System ready []\n");

        try {
            $response = $this->withHeaders($this->authHeaders())->getJson('/api/admin/system-status');
        } finally {
            @unlink($logFile);
            Carbon::setTestNow();
        }

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonPath('data.type', 'system-status');
        $response->assertJsonPath('data.id', 'system');
        $response->assertJsonPath('data.attributes.configuration.count', 2);
        $response->assertJsonPath('data.attributes.configuration.settings.0.key', 'tracking_api_key');
        $response->assertJsonPath('data.attributes.queue.total', 2);
        $response->assertJsonPath('data.attributes.queue.counts.completed', 1);
        $response->assertJsonPath('data.attributes.logs.default_channel', config('logging.default'));
    }

    /**
     * @return array<string,string>
     */
    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer secret-token'];
    }
}
