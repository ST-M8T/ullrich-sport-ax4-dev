<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Exports;

final class ShipmentExportResult
{
    /**
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string>>  $rows
     */
    public function __construct(
        private readonly array $headers,
        private readonly array $rows,
        private readonly int $orderCount,
    ) {}

    /**
     * @return array<int,string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return array<int,array<int,string>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function orderCount(): int
    {
        return $this->orderCount;
    }

    public function toCsvString(string $delimiter = ';'): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create temporary stream for CSV export.');
        }

        fputcsv($handle, $this->headers, $delimiter);

        foreach ($this->rows as $row) {
            fputcsv($handle, $row, $delimiter);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new \RuntimeException('Failed to read generated CSV export.');
        }

        return $csv;
    }

    public function isEmpty(): bool
    {
        return $this->orderCount === 0;
    }
}
