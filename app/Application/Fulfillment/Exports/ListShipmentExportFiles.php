<?php

namespace App\Application\Fulfillment\Exports;

use DateTimeImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

final class ListShipmentExportFiles
{
    public function __construct(private readonly FilesystemFactory $filesystem) {}

    /**
     * @return array<int,array{
     *     name: string,
     *     path: string,
     *     size: int|null,
     *     last_modified: DateTimeImmutable|null
     * }>
     */
    public function __invoke(int $limit = 25): array
    {
        $disk = $this->filesystem->disk('local');
        $paths = $disk->files('exports');

        $entries = [];

        foreach ($paths as $path) {
            if (! str_ends_with($path, '.csv')) {
                continue;
            }

            $size = $disk->size($path);
            $modified = $disk->lastModified($path);

            $entries[] = [
                'name' => basename($path),
                'path' => $path,
                'size' => $size !== false ? (int) $size : null,
                'last_modified' => $modified !== false
                    ? DateTimeImmutable::createFromFormat('U', (string) $modified) ?: null
                    : null,
            ];
        }

        usort(
            $entries,
            static fn (array $a, array $b) => ($b['last_modified']?->getTimestamp() ?? 0) <=> ($a['last_modified']?->getTimestamp() ?? 0)
        );

        return array_slice($entries, 0, max(1, $limit));
    }
}
