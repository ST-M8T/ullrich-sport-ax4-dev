<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use App\Infrastructure\Persistence\Monitoring\Eloquent\SystemJobModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminSetupPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_system_status_summary(): void
    {
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
            'updated_at' => now(),
        ]);

        SystemJobModel::query()->create([
            'job_name' => 'tracking:sync',
            'status' => 'completed',
            'scheduled_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(9),
            'finished_at' => now()->subMinutes(8),
            'duration_ms' => 1200,
            'payload' => ['batch' => 10],
            'result' => ['synced' => 10],
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

        $user = UserModel::query()->create([
            'username' => 'config-admin',
            'display_name' => 'Config Admin',
            'email' => 'config@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'configuration',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin/setup');

        $response->assertOk();
        // Anwendungs-UI ist deutsch (Ubiquitous Language). Header lautet
        // "System-Setup & Monitoring" mit Health-Checks-Bereich.
        $response->assertSee('System-Setup', false);
        $response->assertSee('Health-Checks', false);
        $response->assertSee('tracking_api_key');
        $response->assertSee('tracking_api_secret');
        $response->assertSee('tracking:sync');
        $response->assertSee('pending');
        $response->assertSee('completed');

        Carbon::setTestNow();
    }
}
