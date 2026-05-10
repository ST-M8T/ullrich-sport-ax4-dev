<?php

namespace App\Http\Controllers\Monitoring;

use App\Application\Monitoring\LogViewerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class LogController
{
    public function __construct(private readonly LogViewerService $logs) {}

    public function index(Request $request): View
    {
        $selectedFile = $request->string('file')->trim()->toString();
        $limit = max(50, min(500, (int) $request->integer('limit', 200)));
        $severity = $request->string('severity')->trim()->toString();
        $from = $request->string('from')->trim()->toString();
        $to = $request->string('to')->trim()->toString();

        $error = null;
        try {
            $tail = $this->logs->tail(
                $selectedFile !== '' ? $selectedFile : null,
                [
                    'severity' => $severity !== '' ? $severity : null,
                    'from' => $from !== '' ? $from : null,
                    'to' => $to !== '' ? $to : null,
                ],
                $limit
            );
        } catch (\InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $selectedFile = '';
            $tail = $this->logs->tail(null, [], $limit);
        }

        return view('monitoring.logs.index', [
            'files' => $this->logs->files(),
            'selectedFile' => $tail['file'],
            'logEntries' => $tail['entries'],
            'metadata' => [
                'size' => $tail['size'],
                'modified_at' => $tail['modified_at'],
                'path' => $tail['path'],
                'limit' => $limit,
            ],
            'filters' => [
                'file' => $selectedFile,
                'severity' => $severity,
                'from' => $from,
                'to' => $to,
                'limit' => $limit,
            ],
            'pageTitle' => 'System Logs',
            'currentSection' => 'monitoring-logs',
            'errorMessage' => $error,
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $file = $request->string('file')->trim()->toString();
        $path = $this->logs->path($file !== '' ? $file : null);
        if (! is_file($path)) {
            abort(404, 'Logdatei nicht gefunden.');
        }
        $name = basename($path);

        return response()->download($path, $name, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
