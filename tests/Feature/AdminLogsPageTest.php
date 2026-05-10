<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminLogsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_logs_by_severity_and_date(): void
    {
        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir.DIRECTORY_SEPARATOR.'test.log';
        $contents = <<<'LOG'
[2024-12-01 10:00:00] local.INFO: Informational message []
[2024-12-01 11:00:00] local.ERROR: Something failed {"foo":"bar"}
Stack trace line 1
Stack trace line 2
[2024-12-02 12:00:00] local.WARNING: Warning example []
LOG;

        File::put($logFile, $contents);

        try {
            $user = UserModel::query()->create([
                'username' => 'support',
                'display_name' => 'Support User',
                'email' => 'support@example.com',
                'password_hash' => bcrypt('password'),
                'role' => 'support',
                'must_change_password' => false,
                'disabled' => false,
            ]);

            $this->actingAs($user);

            $response = $this->get('/admin/logs?file=test.log&severity=error&from=2024-12-01T10:30&to=2024-12-01T12:00&limit=100');

            $response->assertOk();
            $response->assertSee('Something failed');
            $response->assertSee('ERROR');
            $response->assertDontSee('Informational message');
            $response->assertDontSee('Warning example');
        } finally {
            @unlink($logFile);
        }
    }
}
