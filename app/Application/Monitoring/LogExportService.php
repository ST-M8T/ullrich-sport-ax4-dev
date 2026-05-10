<?php

namespace App\Application\Monitoring;

use DateTimeImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use InvalidArgumentException;

final class LogExportService
{
    public function __construct(
        private readonly LogViewerService $logs,
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * @return array{
     *     source: string,
     *     path: string,
     *     disk: string,
     *     size: int|null
     * }
     */
    public function export(?string $file = null, string $disk = 'local', string $directory = 'exports/logs'): array
    {
        $source = $this->logs->path($file);
        if (! is_file($source)) {
            throw new InvalidArgumentException('Die angeforderte Logdatei wurde nicht gefunden.');
        }

        $directory = $this->normalizeDirectory($directory);
        $adapter = $this->filesystem->disk($disk);

        if ($directory !== '' && ! $adapter->exists($directory)) {
            $adapter->makeDirectory($directory);
        }

        $timestamp = (new DateTimeImmutable)->format('Ymd_His');
        $targetName = sprintf('%s_%s', $timestamp, basename($source));
        $targetPath = $directory !== '' ? $directory.'/'.$targetName : $targetName;

        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new \RuntimeException('Die Logdatei konnte nicht gelesen werden.');
        }

        $adapter->put($targetPath, $contents);

        $size = $adapter->size($targetPath);

        return [
            'source' => $source,
            'path' => $targetPath,
            'disk' => $disk,
            'size' => $size !== false ? (int) $size : null,
        ];
    }

    private function normalizeDirectory(string $directory): string
    {
        $directory = trim($directory);

        if ($directory === '' || $directory === '/') {
            return '';
        }

        $directory = str_replace('\\', '/', $directory);
        $directory = trim($directory, '/');

        if ($directory === '') {
            return '';
        }

        if (str_contains($directory, '../')) {
            throw new InvalidArgumentException('Das Zielverzeichnis darf keine relativen Pfadelemente enthalten.');
        }

        return $directory;
    }
}
