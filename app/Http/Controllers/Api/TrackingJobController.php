<?php

namespace App\Http\Controllers\Api;

use App\Application\Tracking\Queries\ListTrackingJobs;
use App\Application\Tracking\Resources\TrackingJobResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackingJobController
{
    public function __construct(private readonly ListTrackingJobs $listJobs) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'job_type' => $request->query('job_type'),
            'status' => $request->query('status'),
        ], fn ($value) => $value !== null && $value !== '');

        $jobs = iterator_to_array(($this->listJobs)($filters), false);

        return response()->json([
            'data' => array_map(
                static fn ($job) => TrackingJobResource::fromJob($job)->toArray(),
                $jobs
            ),
        ]);
    }
}
