<?php

namespace App\Http\Controllers\Fulfillment;

use App\Application\Fulfillment\Exports\ListShipmentExportFiles;
use App\Application\Fulfillment\Exports\ListShipmentExportJobs;
use App\Application\Fulfillment\Exports\ShipmentExportManager;
use App\Application\Monitoring\SystemJobLifecycleService;
use App\Domain\Fulfillment\Exports\ShipmentExportFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExportController
{
    public function __construct(
        private readonly ShipmentExportManager $manager,
        private readonly ListShipmentExportFiles $files,
        private readonly ListShipmentExportJobs $jobs,
        private readonly SystemJobLifecycleService $jobLifecycle,
    ) {}

    public function index(Request $request): View
    {
        $filters = ShipmentExportFilters::fromArray($request->query());
        $jobStatus = $request->query('job_status');
        $jobStatus = is_string($jobStatus) ? $jobStatus : null;

        $files = ($this->files)();
        $files = array_map(function (array $file): array {
            $file['download_token'] = $this->encodePath($file['path']);

            return $file;
        }, $files);

        $jobs = ($this->jobs)(20, $jobStatus);
        $jobs = array_map(function (array $job): array {
            $job['download_token'] = $job['file'] ? $this->encodePath($job['file']) : null;

            return $job;
        }, $jobs);

        return view('fulfillment.exports.index', [
            'pageTitle' => 'CSV-Export',
            'currentSection' => 'csv-export',
            'filters' => $filters,
            'filterValues' => $filters->toArray(),
            'jobStatus' => $jobStatus,
            'files' => $files,
            'jobs' => $jobs,
            'recentExport' => session()->get('recent_export'),
        ]);
    }

    public function trigger(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'min:1'],
            'processed_from' => ['nullable', 'date'],
            'processed_to' => ['nullable', 'date', 'after_or_equal:processed_from'],
            'sender_code' => ['nullable', 'string', 'max:64'],
            'destination_country' => ['nullable', 'string', 'size:2'],
            'is_booked' => ['nullable', 'boolean'],
        ]);

        $filters = ShipmentExportFilters::fromArray($validated);
        $orderId = isset($validated['order_id']) ? (int) $validated['order_id'] : null;
        $userId = $request->user()?->getAuthIdentifier();
        $userId = is_numeric($userId) ? (int) $userId : null;

        try {
            $result = $this->manager->trigger($filters, $orderId, $userId);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Export konnte nicht gestartet werden: '.$e->getMessage());
        }

        session()->flash('success', 'Export gestartet. Datei wird bereitgestellt.');
        session()->flash('recent_export', [
            'file_path' => $result['file_path'],
            'file_token' => $this->encodePath($result['file_path']),
            'orders_total' => $result['orders_total'],
        ]);

        return redirect()->route('csv-export', $this->buildRedirectQuery($validated, $request));
    }

    public function retry(int $job, Request $request): RedirectResponse
    {
        $entry = $this->jobLifecycle->find($job);
        if (! $entry || $entry->jobName() !== 'fulfillment.csv_export') {
            abort(404);
        }

        $userId = $request->user()?->getAuthIdentifier();
        $userId = is_numeric($userId) ? (int) $userId : null;

        try {
            $result = $this->manager->retryFromJob($entry, $userId);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', 'Retry fehlgeschlagen: '.$e->getMessage());
        }

        session()->flash('success', 'Export erneut gestartet.');
        session()->flash('recent_export', [
            'file_path' => $result['file_path'],
            'file_token' => $this->encodePath($result['file_path']),
            'orders_total' => $result['orders_total'],
        ]);

        return redirect()->back();
    }

    public function download(Request $request): StreamedResponse
    {
        $token = $request->query('path');
        if (! is_string($token) || $token === '') {
            abort(404);
        }

        $path = $this->decodePath($token);
        if ($path === null || ! str_starts_with($path, 'exports/')) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(404);
        }

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            abort(404);
        }

        return response()->streamDownload(
            function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            },
            basename($path),
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildRedirectQuery(array $validated, Request $request): array
    {
        $query = [];
        foreach (['processed_from', 'processed_to', 'sender_code', 'destination_country', 'is_booked'] as $key) {
            if (! empty($validated[$key])) {
                $query[$key] = $validated[$key];
            }
        }

        $jobStatus = $request->query('job_status');
        if (is_string($jobStatus) && $jobStatus !== '') {
            $query['job_status'] = $jobStatus;
        }

        return $query;
    }

    private function encodePath(string $path): string
    {
        $encoded = base64_encode($path);

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    private function decodePath(string $token): ?string
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
