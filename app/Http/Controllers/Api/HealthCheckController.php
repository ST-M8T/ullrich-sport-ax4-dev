<?php

namespace App\Http\Controllers\Api;

use App\Application\Monitoring\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

final class HealthCheckController extends Controller
{
    public function __construct(private readonly HealthCheckService $service) {}

    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => Carbon::now()->toIso8601String(),
        ], Response::HTTP_OK);
    }

    public function ready(): JsonResponse
    {
        $checks = $this->service->checks();
        $overall = $this->determineOverallStatus($checks);

        $statusCode = $overall === 'fail'
            ? Response::HTTP_SERVICE_UNAVAILABLE
            : Response::HTTP_OK;

        return response()->json([
            'status' => $overall,
            'timestamp' => Carbon::now()->toIso8601String(),
            'checks' => $checks,
        ], $statusCode);
    }

    /**
     * @param  array<string,array<string,mixed>>  $checks
     */
    private function determineOverallStatus(array $checks): string
    {
        $statuses = array_map(
            static fn (array $check): string => strtolower((string) ($check['status'] ?? 'unknown')),
            $checks
        );

        if (in_array('fail', $statuses, true)) {
            return 'fail';
        }

        if (in_array('warn', $statuses, true)) {
            return 'warn';
        }

        return 'ok';
    }
}
