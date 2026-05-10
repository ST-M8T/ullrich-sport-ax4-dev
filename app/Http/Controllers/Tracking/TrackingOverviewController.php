<?php

namespace App\Http\Controllers\Tracking;

use App\Application\Tracking\Queries\ListTrackingAlerts;
use App\Application\Tracking\Queries\ListTrackingJobs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class TrackingOverviewController
{
    public function __construct(
        private readonly ListTrackingJobs $listJobs,
        private readonly ListTrackingAlerts $listAlerts,
    ) {}

    public function index(Request $request): View
    {
        $jobFilters = [
            'job_type' => $request->string('job_type')->trim()->toString(),
            'status' => $request->string('job_status')->trim()->toString(),
        ];

        $alertFilters = [
            'alert_type' => $request->string('alert_type')->trim()->toString(),
            'severity' => $request->string('alert_severity')->trim()->toString(),
            'channel' => $request->string('alert_channel')->trim()->toString(),
        ];

        if ($request->filled('alert_is_acknowledged')) {
            $alertFilters['is_acknowledged'] = (bool) (int) $request->input('alert_is_acknowledged');
        }

        $jobs = ($this->listJobs)(array_filter($jobFilters, static fn ($value) => $value !== ''));
        $alerts = ($this->listAlerts)(array_filter($alertFilters, static fn ($value) => $value !== ''));

        return view('tracking.overview', [
            'jobs' => $jobs,
            'alerts' => $alerts,
            'jobFilters' => $jobFilters,
            'alertFilters' => $alertFilters,
        ]);
    }
}
