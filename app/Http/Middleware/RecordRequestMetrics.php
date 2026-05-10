<?php

namespace App\Http\Middleware;

use App\Application\Monitoring\Metrics\MetricsRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordRequestMetrics
{
    public function __construct(private readonly MetricsRecorder $metrics) {}

    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = null;
        $thrown = null;

        try {
            $response = $next($request);

            return $response;
        } catch (Throwable $exception) {
            $thrown = $exception;
            throw $exception;
        } finally {
            $duration = (microtime(true) - $start) * 1000;
            $status = $response?->getStatusCode() ?? 500;

            $routeName = $request->route()?->getName();
            $routeName ??= $request->route()?->uri();
            $routeName ??= $request->path();

            $routeName = $routeName === '' ? 'root' : $routeName;

            $tags = [
                'method' => strtolower($request->getMethod()),
                'route' => $this->sanitizeTag((string) $routeName),
                'status' => $status,
            ];

            if ($thrown !== null) {
                $tags['exception'] = class_basename($thrown);
            }

            $this->metrics->increment('http.request.count', 1, $tags);
            $this->metrics->timing('http.request.duration', (float) $duration, $tags);
        }
    }

    private function sanitizeTag(string $value): string
    {
        return strtolower(str_replace(['{', '}', ' '], ['_', '_', '_'], trim($value)));
    }
}
