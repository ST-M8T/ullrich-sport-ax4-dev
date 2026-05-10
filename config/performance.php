<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Masterdata Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure cache behaviour and default pagination settings for masterdata
    | heavy endpoints. Values can be tuned per environment via .env settings.
    |
    */

    'masterdata' => [
        'cache_key' => env('MASTERDATA_CACHE_KEY', 'masterdata:catalog'),
        'cache_ttl' => (int) env('MASTERDATA_CACHE_TTL', 300),
        'page_size' => (int) env('MASTERDATA_PAGE_SIZE', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Performance Settings
    |--------------------------------------------------------------------------
    |
    | Monitoring views frequently query large tables. These defaults keep the
    | amount of retrieved data predictable while still allowing configuration.
    |
    */

    'monitoring' => [
        'cache_ttl' => (int) env('MONITORING_CACHE_TTL', 120),
        'page_size' => (int) env('MONITORING_PAGE_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Defaults
    |--------------------------------------------------------------------------
    |
    | Supervisor/queue worker defaults used throughout documentation and when
    | dispatching queue warmers. Exposed here to keep everything centralised.
    |
    */

    'queue' => [
        'processes' => (int) env('QUEUE_WORKER_PROCESSES', 4),
        'tries' => (int) env('QUEUE_WORKER_TRIES', 3),
        'timeout' => (int) env('QUEUE_WORKER_TIMEOUT', 60),
        'sleep' => (int) env('QUEUE_WORKER_SLEEP', 3),
        'maintenance_queue' => env('QUEUE_MAINTENANCE', 'maintenance'),
    ],

];
