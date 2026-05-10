<?php

namespace App\Console\Commands\Monitoring;

use App\Application\Monitoring\LogExportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ExportLogsCommand extends Command
{
    protected $signature = 'logs:export
        {file? : Name der Logdatei (Standard: laravel.log)}
        {--disk=local : Ziel-Storage-Disk}
        {--directory=exports/logs : Zielverzeichnis auf der Disk}';

    protected $description = 'Exportiert eine Logdatei in den angegebenen Storage-Bereich';

    public function __construct(private readonly LogExportService $exporter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = $this->argument('file');
        $disk = (string) $this->option('disk');
        $directory = (string) $this->option('directory');

        $this->components->info('Starte Log-Export');
        $this->line(sprintf('Quelle: %s | Disk: %s | Ziel: %s', $file ?: 'laravel.log', $disk, $directory));

        try {
            $result = $this->exporter->export(
                $file !== null ? (string) $file : null,
                $disk,
                $directory
            );
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->components->error('Export fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->success(sprintf(
            'Export abgeschlossen: %s (%s) → %s',
            basename($result['source']),
            $this->formatSize($result['size']),
            $result['path']
        ));

        return self::SUCCESS;
    }

    private function formatSize(?int $size): string
    {
        if ($size === null) {
            return 'unbekannte Größe';
        }

        if ($size < 1024) {
            return sprintf('%d B', $size);
        }

        if ($size < 1_048_576) {
            return sprintf('%.1f KiB', $size / 1024);
        }

        return sprintf('%.1f MiB', $size / 1_048_576);
    }
}
