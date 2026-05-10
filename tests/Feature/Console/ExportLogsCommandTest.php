<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ExportLogsCommandTest extends TestCase
{
    public function test_logfile_is_exported_to_storage_disk(): void
    {
        Storage::fake('local');

        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir.DIRECTORY_SEPARATOR.'laravel.log';
        file_put_contents($logFile, "Testeintrag\n");

        $this->artisan('logs:export')
            ->assertExitCode(0);

        $files = Storage::disk('local')->files('exports/logs');
        $this->assertNotEmpty($files);

        $content = Storage::disk('local')->get($files[0]);
        $this->assertStringContainsString('Testeintrag', $content);
    }
}
