<?php

namespace App\Application\Monitoring;

use App\Application\Configuration\SystemSettingService;
use App\Domain\Configuration\SystemSetting;
use App\Domain\Monitoring\SystemJobEntry;
use Illuminate\Filesystem\Filesystem;

final class SystemStatusService
{
    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly SystemJobLifecycleService $systemJobs,
        private readonly HealthCheckService $healthChecks,
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * @return array{
     *     configuration: array<string,mixed>,
     *     queue: array<string,mixed>,
     *     logs: array<string,mixed>,
     *     versions: array<string,mixed>,
     *     health: array<string,mixed>
     * }
     */
    public function status(): array
    {
        $configuration = $this->mapSettings($this->settings->all());
        $queue = $this->buildQueueSummary();
        $logs = $this->describeLogDirectories();
        $healthChecks = $this->healthChecks->checks();

        return [
            'configuration' => [
                'settings' => $configuration,
                'count' => count($configuration),
            ],
            'queue' => $queue,
            'logs' => $logs,
            'versions' => $this->versionInformation(),
            'health' => [
                'status' => $this->determineOverallStatus($healthChecks),
                'checks' => $healthChecks,
            ],
        ];
    }

    /**
     * @param  array<int,SystemSetting>  $settings
     * @return array<int,array<string,mixed>>
     */
    private function mapSettings(array $settings): array
    {
        return array_map(
            static fn (SystemSetting $setting) => [
                'key' => $setting->key(),
                'value' => $setting->isSecret() ? '••••••' : $setting->rawValue(),
                'is_secret' => $setting->isSecret(),
                'value_type' => $setting->valueType(),
                'updated_by' => $setting->updatedByUserId(),
                'updated_at' => $setting->updatedAt(),
                'is_configured' => $setting->rawValue() !== null && $setting->rawValue() !== '',
            ],
            $settings
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function buildQueueSummary(): array
    {
        $summary = $this->systemJobs->summarize();

        return [
            'counts' => $summary['counts'],
            'total' => $summary['total'],
            'recent' => $this->mapRecentJobs($summary['recent']),
            'default_connection' => config('queue.default'),
        ];
    }

    /**
     * @param  array<int,SystemJobEntry>  $recent
     * @return array<int,array<string,mixed>>
     */
    private function mapRecentJobs(array $recent): array
    {
        return array_map(
            static fn (SystemJobEntry $job) => [
                'id' => $job->id(),
                'name' => $job->jobName(),
                'status' => $job->status(),
                'scheduled_at' => $job->scheduledAt(),
                'started_at' => $job->startedAt(),
                'finished_at' => $job->finishedAt(),
                'duration_ms' => $job->durationMs(),
                'error' => $job->errorMessage(),
            ],
            $recent
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function describeLogDirectories(): array
    {
        $paths = $this->logDirectories();

        $directories = array_map(
            fn (string $path) => $this->inspectDirectory($path),
            $paths
        );

        return [
            'default_channel' => config('logging.default'),
            'directories' => $directories,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function logDirectories(): array
    {
        $paths = [storage_path('logs')];

        $channels = (array) config('logging.channels', []);
        foreach ($channels as $channel) {
            if (is_array($channel) && isset($channel['path']) && is_string($channel['path'])) {
                $paths[] = (string) dirname($channel['path']);
            }
        }

        $paths = array_values(array_unique(array_map(
            static fn ($path) => rtrim((string) $path, DIRECTORY_SEPARATOR),
            $paths
        )));

        sort($paths);

        return $paths;
    }

    /**
     * @return array<string,mixed>
     */
    private function inspectDirectory(string $path): array
    {
        $exists = $this->filesystem->exists($path);
        $isDirectory = $exists && $this->filesystem->isDirectory($path);

        $fileCount = 0;
        $totalSize = 0;
        $latestMTime = null;

        if ($isDirectory) {
            foreach ($this->filesystem->files($path) as $file) {
                $fileCount++;
                $totalSize += $file->getSize();
                $latestMTime = max($latestMTime ?? 0, $file->getMTime());
            }
        }

        $timezone = config('app.timezone') ?: 'UTC';
        $lastModified = $latestMTime ? (new \DateTimeImmutable('@'.$latestMTime))->setTimezone(new \DateTimeZone($timezone)) : null;

        return [
            'path' => $path,
            'exists' => $exists,
            'writable' => $exists ? is_writable($path) : false,
            'is_directory' => $isDirectory,
            'file_count' => $fileCount,
            'size_bytes' => $totalSize,
            'last_modified_at' => $lastModified,
        ];
    }

    /**
     * @return array<string,string|bool|null>
     */
    private function versionInformation(): array
    {
        return [
            'app_name' => config('app.name'),
            'environment' => app()->environment(),
            'debug' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'queue_connection' => config('queue.default'),
            'log_channel' => config('logging.default'),
            'commit_hash' => config('app.commit_hash'),
        ];
    }

    /**
     * @param  array<string,array<string,mixed>>  $checks
     */
    private function determineOverallStatus(array $checks): string
    {
        $statuses = array_map(
            static fn (array $check): string => strtolower((string) ($check['status'] ?? 'unknown')),
            $checks
        );

        if (in_array('fail', $statuses, true)) {
            return 'fail';
        }

        if (in_array('warn', $statuses, true)) {
            return 'warn';
        }

        return 'ok';
    }
}
