<?php

namespace Tests\Feature\Api\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class AdminLogFileApiTest extends TestCase
{
    use RefreshDatabase;

    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin_api.token' => 'secret-token']);
        $this->logDir = storage_path('logs');

        if (! is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (glob($this->logDir.DIRECTORY_SEPARATOR.'admin-test*.log') as $file) {
            @unlink($file);
        }
    }

    public function test_log_file_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/log-files');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
    }

    public function test_can_list_log_files(): void
    {
        File::put($this->logDir.DIRECTORY_SEPARATOR.'admin-test.log', 'Example content');

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/admin/log-files');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonPath('data.0.type', 'log-files');
        $response->assertJsonFragment(['id' => 'admin-test.log']);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.count'));
    }

    public function test_can_filter_log_entries_by_severity(): void
    {
        $contents = <<<'LOG'
[2024-12-01 10:00:00] local.INFO: Informational message []
[2024-12-01 11:00:00] local.ERROR: Something failed {"foo":"bar"}
Stack trace line 1
[2024-12-01 12:00:00] local.WARNING: Warning example []
LOG;
        File::put($this->logDir.DIRECTORY_SEPARATOR.'admin-test.log', $contents);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/log-files/admin-test.log/entries?severity=error&limit=5');

        $response->assertOk();
        $response->assertJsonPath('data.0.type', 'log-entries');
        $response->assertJsonPath('data.0.attributes.severity', 'error');
        $response->assertJsonPath('meta.file', 'admin-test.log');
        $response->assertJsonPath('meta.limit', 5);
        $response->assertJsonCount(1, 'data');
    }

    public function test_download_action_returns_download_url(): void
    {
        File::put($this->logDir.DIRECTORY_SEPARATOR.'admin-test.log', 'Example content');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/log-files/admin-test.log/actions/download');

        $response->assertOk();
        $response->assertJsonPath('data.type', 'log-file-actions');
        $response->assertJsonPath('data.attributes.method', 'GET');
        $response->assertJsonPath('data.attributes.download_url', fn ($url) => str_contains($url, '/admin/logs/download'));
    }

    public function test_can_delete_log_file(): void
    {
        $path = $this->logDir.DIRECTORY_SEPARATOR.'admin-test.log';
        File::put($path, 'Example content');

        $response = $this->withHeaders($this->authHeaders())->delete('/api/admin/log-files/admin-test.log');

        $response->assertNoContent();
        $this->assertFileDoesNotExist($path);
    }

    public function test_missing_log_file_returns_not_found_error(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/log-files/non-existent.log/actions/download');

        $response->assertStatus(404);
        $response->assertJsonPath('errors.0.status', '404');
    }

    /**
     * @return array<string,string>
     */
    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer secret-token'];
    }
}
