<?php

namespace App\Http\Controllers\Monitoring;

use App\Application\Monitoring\SystemStatusService;
use Illuminate\Contracts\View\View;

final class SetupController
{
    public function __construct(private readonly SystemStatusService $status) {}

    public function index(): View
    {
        return view('monitoring.setup.index', [
            'status' => $this->status->status(),
        ]);
    }
}
