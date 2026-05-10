<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Monitoring\SystemStatusService;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;

final class SystemStatusController
{
    use InteractsWithJsonApiResponses;

    public function __construct(private readonly SystemStatusService $status) {}

    public function __invoke(): JsonResponse
    {
        $status = $this->status->status();

        return $this->jsonApiResponse([
            'data' => [
                'type' => 'system-status',
                'id' => 'system',
                'attributes' => $this->transformStatus($status),
            ],
        ]);
    }

    /**
     * @param  array<string,mixed>  $status
     * @return array<string,mixed>
     */
    private function transformStatus(array $status): array
    {
        return [
            'configuration' => [
                'count' => (int) ($status['configuration']['count'] ?? 0),
                'settings' => array_map(
                    fn (array $setting): array => [
                        'key' => $setting['key'] ?? null,
                        'value' => $setting['value'] ?? null,
                        'value_type' => $setting['value_type'] ?? null,
                        'updated_by_user_id' => $setting['updated_by'] ?? null,
                        'updated_at' => $this->formatDate($setting['updated_at'] ?? null),
                        'is_configured' => (bool) ($setting['is_configured'] ?? false),
                    ],
                    $status['configuration']['settings'] ?? []
                ),
            ],
            'queue' => [
                'counts' => $status['queue']['counts'] ?? [],
                'total' => (int) ($status['queue']['total'] ?? 0),
                'default_connection' => $status['queue']['default_connection'] ?? null,
                'recent' => array_map(
                    fn (array $job): array => [
                        'id' => $job['id'] ?? null,
                        'name' => $job['name'] ?? null,
                        'status' => $job['status'] ?? null,
                        'scheduled_at' => $this->formatDate($job['scheduled_at'] ?? null),
                        'started_at' => $this->formatDate($job['started_at'] ?? null),
                        'finished_at' => $this->formatDate($job['finished_at'] ?? null),
                        'duration_ms' => $job['duration_ms'] ?? null,
                        'error' => $job['error'] ?? null,
                    ],
                    $status['queue']['recent'] ?? []
                ),
            ],
            'logs' => [
                'default_channel' => $status['logs']['default_channel'] ?? null,
                'directories' => array_map(
                    fn (array $directory): array => [
                        'path' => $directory['path'] ?? null,
                        'exists' => (bool) ($directory['exists'] ?? false),
                        'writable' => (bool) ($directory['writable'] ?? false),
                        'is_directory' => (bool) ($directory['is_directory'] ?? false),
                        'file_count' => (int) ($directory['file_count'] ?? 0),
                        'size_bytes' => (int) ($directory['size_bytes'] ?? 0),
                        'last_modified_at' => $this->formatDate($directory['last_modified_at'] ?? null),
                    ],
                    $status['logs']['directories'] ?? []
                ),
            ],
            'versions' => $status['versions'] ?? [],
        ];
    }
}
