<?php

namespace App\Application\Monitoring;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

final class LogViewerService
{
    private const DEFAULT_LIMIT = 200;

    private const DEFAULT_FILE = 'laravel.log';

    private const MAX_TAIL_BYTES = 2_097_152; // 2 MiB

    public function __construct(private readonly Filesystem $filesystem) {}

    public function path(?string $file = null): string
    {
        return $this->resolvePath($file);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function files(): array
    {
        $basePath = $this->basePath();
        if (! $this->filesystem->exists($basePath)) {
            return [];
        }

        $timezone = new DateTimeZone(config('app.timezone') ?: 'UTC');

        $files = [];
        foreach ($this->filesystem->files($basePath) as $file) {
            $files[] = [
                'name' => $file->getFilename(),
                'path' => $this->relativePath($file->getPathname()),
                'size' => $file->getSize(),
                'modified_at' => (new DateTimeImmutable('@'.$file->getMTime()))->setTimezone($timezone),
            ];
        }

        usort(
            $files,
            static fn (array $left, array $right): int => ($right['modified_at'] <=> $left['modified_at'])
        );

        return $files;
    }

    public function delete(?string $file = null): void
    {
        $path = $this->resolvePath($file);

        if (! is_file($path)) {
            throw new InvalidArgumentException('Logdatei nicht gefunden.');
        }

        if (! $this->filesystem->delete($path)) {
            throw new RuntimeException('Logdatei konnte nicht gelöscht werden.');
        }
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function tail(?string $file = null, array $filters = [], int $limit = self::DEFAULT_LIMIT): array
    {
        $path = $this->resolvePath($file);
        $limit = max(1, min(1000, $limit));

        $raw = $this->readTail($path, self::MAX_TAIL_BYTES);
        $entries = $this->parseEntries($raw);
        $entries = $this->applyFilters($entries, $filters);

        $entries = array_slice(array_reverse($entries), 0, $limit);

        $fullPath = realpath($path) ?: $path;
        $timezone = new DateTimeZone(config('app.timezone') ?: 'UTC');
        $modifiedAt = is_file($path)
            ? (new DateTimeImmutable('@'.filemtime($path)))->setTimezone($timezone)
            : null;

        return [
            'file' => $this->relativePath($fullPath),
            'path' => $fullPath,
            'entries' => $entries,
            'size' => is_file($path) ? filesize($path) : 0,
            'modified_at' => $modifiedAt,
        ];
    }

    private function basePath(): string
    {
        return storage_path('logs');
    }

    private function resolvePath(?string $file): string
    {
        $file = $file ?: self::DEFAULT_FILE;
        $file = ltrim($file, DIRECTORY_SEPARATOR);

        if (str_contains($file, '..')) {
            throw new InvalidArgumentException('Ungültiger Dateiname.');
        }

        return rtrim($this->basePath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;
    }

    private function relativePath(string $path): string
    {
        $base = $this->basePath();
        $normalizedBase = rtrim(realpath($base) ?: $base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $normalizedPath = realpath($path) ?: $path;

        if (str_starts_with($normalizedPath, $normalizedBase)) {
            return ltrim(substr($normalizedPath, strlen($normalizedBase)), DIRECTORY_SEPARATOR);
        }

        return basename($normalizedPath);
    }

    private function readTail(string $path, int $bytes): string
    {
        $size = is_file($path) ? filesize($path) : 0;
        if ($size === 0) {
            return '';
        }

        $bytes = max(1, min($bytes, $size));
        $offset = max(0, $size - $bytes);

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        try {
            if (fseek($handle, $offset) !== 0) {
                rewind($handle);
            }

            $content = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        return $content ?: '';
    }

    /**
     * @param  array<int,array<string,mixed>>  $entries
     * @param  array<string,mixed>  $filters
     * @return array<int,array<string,mixed>>
     */
    private function applyFilters(array $entries, array $filters): array
    {
        $severity = isset($filters['severity']) ? strtolower((string) $filters['severity']) : null;
        $from = $this->parseDate($filters['from'] ?? null);
        $to = $this->parseDate($filters['to'] ?? null);

        return array_values(array_filter(
            $entries,
            static function (array $entry) use ($severity, $from, $to): bool {
                if ($severity && $entry['severity'] !== $severity) {
                    return false;
                }

                if ($from && $entry['datetime'] && $entry['datetime'] < $from) {
                    return false;
                }

                if ($to && $entry['datetime'] && $entry['datetime'] > $to) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseEntries(string $contents): array
    {
        if ($contents === '') {
            return [];
        }

        $lines = preg_split('/\\r\\n|\\r|\\n/', $contents) ?: [];
        $entries = [];
        $current = null;
        $timezone = new DateTimeZone(config('app.timezone') ?: 'UTC');

        foreach ($lines as $line) {
            $line = (string) $line;
            if ($line === '') {
                if ($current) {
                    $current['stack'][] = '';
                }

                continue;
            }

            if (preg_match('/^\\[(?<datetime>\\d{4}-\\d{2}-\\d{2} [0-9:.]+)\\] (?<env>[\\w.-]+)\\.(?<level>[A-Z]+): (?<message>.*)$/', $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $dateTime = null;
                try {
                    $dateTime = (new DateTimeImmutable($matches['datetime']))->setTimezone($timezone);
                } catch (\Throwable) {
                    $dateTime = null;
                }

                $context = null;
                $message = $matches['message'];

                if (preg_match('/^([^\\{\\[]+)(?<context> \\{.*)$/', $message, $messageMatches)) {
                    $message = rtrim($messageMatches[1]);
                    $context = trim($messageMatches['context']);
                }

                $current = [
                    'datetime' => $dateTime,
                    'environment' => strtolower($matches['env']),
                    'severity' => strtolower($matches['level']),
                    'message' => $message,
                    'context' => $context,
                    'stack' => [],
                    'raw' => $line,
                ];
            } elseif ($current !== null) {
                $current['stack'][] = $line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
