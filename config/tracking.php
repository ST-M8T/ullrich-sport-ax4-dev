<?php

return [
    'jobs' => [
        'recurring' => [
            /*
             * Beispielkonfiguration:
             *
             * 'tracking.sync_shipments' => [
             *     'job_type' => 'tracking.sync_shipments',
             *     'frequency' => 'PT15M',
             *     'payload' => [],
             *     'retry' => [
             *         'max_attempts' => 3,
             *         'backoff' => 'PT10M',
             *     ],
             *     'alert' => [
             *         'threshold' => 3,
             *         'alert_type' => 'tracking.job.failure',
             *         'severity' => 'error',
             *     ],
             * ],
             */
        ],
        'policies' => [
            /*
             * Weitere Policies für Systemjobs ohne Scheduler können hier hinterlegt werden.
             */
        ],
    ],
];
