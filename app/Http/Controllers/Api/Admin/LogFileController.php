<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Monitoring\LogViewerService;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class LogFileController
{
    use InteractsWithJsonApiResponses;

    public function __construct(private readonly LogViewerService $logs) {}

    public function index(): JsonResponse
    {
        $files = collect($this->logs->files())
            ->map(fn (array $file): array => $this->transformFileMetadata($file))
            ->values()
            ->all();

        return $this->jsonApiResponse([
            'data' => $files,
            'meta' => [
                'count' => count($files),
            ],
        ]);
    }

    public function show(string $file): JsonResponse
    {
        $metadata = $this->findFile($file);
        if (! $metadata) {
            return $this->jsonApiError(404, 'Not Found', sprintf('Log file "%s" was not found.', $file));
        }

        return $this->jsonApiResponse([
            'data' => $this->transformFileMetadata($metadata),
        ]);
    }

    public function entries(string $file, Request $request): JsonResponse
    {
        try {
            $limit = max(1, min(1000, (int) $request->query('limit', 200)));
            $filters = [
                'severity' => $request->query('severity'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ];

            $result = $this->logs->tail($file, $filters, $limit);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonApiError(400, 'Invalid Log File', $exception->getMessage());
        }

        $entries = [];
        foreach ($result['entries'] ?? [] as $index => $entry) {
            $entries[] = $this->transformLogEntry((array) $entry, $result['file'] ?? $file, (int) $index);
        }

        return $this->jsonApiResponse([
            'data' => $entries,
            'meta' => [
                'file' => $result['file'] ?? $file,
                'size_bytes' => $result['size'] ?? 0,
                'modified_at' => $this->formatDate($result['modified_at'] ?? null),
                'limit' => $limit,
            ],
        ]);
    }

    public function download(string $file): JsonResponse
    {
        try {
            $path = $this->logs->path($file);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonApiError(400, 'Invalid Log File', $exception->getMessage());
        }

        if (! is_file($path)) {
            return $this->jsonApiError(404, 'Not Found', sprintf('Log file "%s" was not found.', $file));
        }

        $downloadUrl = route('monitoring-logs.download', ['file' => $file], false);

        return $this->jsonApiResponse([
            'data' => [
                'type' => 'log-file-actions',
                'id' => $file,
                'attributes' => [
                    'download_url' => $downloadUrl,
                    'method' => 'GET',
                ],
            ],
        ]);
    }

    public function destroy(string $file): Response|JsonResponse
    {
        try {
            $path = $this->logs->path($file);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonApiError(400, 'Invalid Log File', $exception->getMessage());
        }

        if (! is_file($path)) {
            return $this->jsonApiError(404, 'Not Found', sprintf('Log file "%s" was not found.', $file));
        }

        $this->logs->delete($file);

        return response()->noContent()->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * @param  array<string,mixed>  $metadata
     * @return array<string, mixed>
     */
    private function transformFileMetadata(array $metadata): array
    {
        $relativePath = (string) ($metadata['path'] ?? $metadata['name'] ?? 'laravel.log');

        return [
            'type' => 'log-files',
            'id' => $relativePath,
            'attributes' => [
                'name' => $metadata['name'] ?? basename($relativePath),
                'path' => $relativePath,
                'size_bytes' => (int) ($metadata['size'] ?? 0),
                'modified_at' => $this->formatDate($metadata['modified_at'] ?? null),
            ],
            'meta' => [
                'download_url' => route('monitoring-logs.download', ['file' => $relativePath], false),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return array<string, mixed>
     */
    private function transformLogEntry(array $entry, string $file, int $index): array
    {
        $id = sprintf('%s:%s', $file, $index);

        return [
            'type' => 'log-entries',
            'id' => $id,
            'attributes' => [
                'timestamp' => $this->formatDate($entry['datetime'] ?? null),
                'environment' => $entry['environment'] ?? null,
                'severity' => $entry['severity'] ?? null,
                'message' => $entry['message'] ?? null,
                'context' => $entry['context'] ?? null,
                'stack' => $entry['stack'] ?? [],
            ],
            'relationships' => [
                'file' => [
                    'data' => [
                        'type' => 'log-files',
                        'id' => $file,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findFile(string $file): ?array
    {
        /** @var Collection<int,array<string,mixed>> $files */
        $files = collect($this->logs->files());

        return $files->first(
            fn (array $metadata): bool => Str::lower((string) ($metadata['path'] ?? '')) === Str::lower($file)
        );
    }
}
