<?php

$dhlString = static function (string $key, ?string $default = ''): ?string {
    $value = env($key);

    if ($value === null) {
        return $default;
    }

    $value = trim((string) $value);

    return $value !== '' ? $value : $default;
};

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'plenty' => [
        'base_url' => env('PLENTY_BASE_URL', ''),
        'username' => env('PLENTY_USERNAME', ''),
        'password' => env('PLENTY_PASSWORD', ''),
        'access_token' => env('PLENTY_ACCESS_TOKEN', ''),
        'timeout' => (float) env('PLENTY_TIMEOUT', 10.0),
        'connect_timeout' => (float) env('PLENTY_CONNECT_TIMEOUT', 5.0),
        'retry' => [
            'times' => (int) env('PLENTY_RETRY_TIMES', 3),
            'sleep' => (int) env('PLENTY_RETRY_SLEEP', 200),
        ],
        'circuit_breaker' => [
            'failures' => (int) env('PLENTY_CIRCUIT_FAILURES', 5),
            'cooldown' => (int) env('PLENTY_CIRCUIT_COOLDOWN', 60),
        ],
        'log_channel' => env('PLENTY_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'ping' => [
            'method' => env('PLENTY_PING_METHOD', 'GET'),
            'path' => env('PLENTY_PING_PATH', '/'),
        ],
        'verify' => (bool) env('PLENTY_VERIFY_SSL', true),
    ],

    'dhl' => [
        'base_url' => env('DHL_BASE_URL', ''),
        'api_key' => env('DHL_API_KEY', ''),
        'timeout' => (float) env('DHL_TIMEOUT', 10.0),
        'connect_timeout' => (float) env('DHL_CONNECT_TIMEOUT', 5.0),
        'retry' => [
            'times' => (int) env('DHL_RETRY_TIMES', 3),
            'sleep' => (int) env('DHL_RETRY_SLEEP', 200),
        ],
        'circuit_breaker' => [
            'failures' => (int) env('DHL_CIRCUIT_FAILURES', 5),
            'cooldown' => (int) env('DHL_CIRCUIT_COOLDOWN', 60),
        ],
        'log_channel' => env('DHL_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'ping' => [
            'method' => env('DHL_PING_METHOD', 'GET'),
            'path' => env('DHL_PING_PATH', '/'),
        ],
        'verify' => (bool) env('DHL_VERIFY_SSL', true),
    ],

    'dhl_auth' => [
        'base_url' => $dhlString('DHL_AUTH_BASE_URL', 'https://api-sandbox.dhl.com'),
        'username' => $dhlString('DHL_AUTH_USERNAME', ''),
        'password' => $dhlString('DHL_AUTH_PASSWORD', ''),
        'path' => $dhlString('DHL_AUTH_PATH', '/auth/v1/token'),
        'token_cache_ttl' => (int) env('DHL_AUTH_TOKEN_CACHE_TTL', 0),
        'timeout' => (float) env('DHL_AUTH_TIMEOUT', 10.0),
        'connect_timeout' => (float) env('DHL_AUTH_CONNECT_TIMEOUT', 5.0),
        'retry' => [
            'times' => (int) env('DHL_AUTH_RETRY_TIMES', 2),
            'sleep' => (int) env('DHL_AUTH_RETRY_SLEEP', 200),
        ],
        'log_channel' => env('DHL_AUTH_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'verify' => (bool) env('DHL_AUTH_VERIFY_SSL', true),
    ],

    'dhl_freight' => [
        'base_url' => $dhlString('DHL_FREIGHT_BASE_URL', 'https://api-sandbox.dhl.com/freight'),
        'api_key' => $dhlString('DHL_FREIGHT_API_KEY', ''),
        'api_secret' => $dhlString('DHL_FREIGHT_API_SECRET', ''),
        'auth' => $dhlString('DHL_FREIGHT_AUTH', 'bearer'),
        'api_key_header' => $dhlString('DHL_FREIGHT_API_KEY_HEADER', 'DHL-API-Key'),
        'api_secret_header' => $dhlString('DHL_FREIGHT_API_SECRET_HEADER', null),
        'paths' => [
            'timetable' => $dhlString('DHL_FREIGHT_TIMETABLE_PATH', '/timetable/gettimetable'),
            'products' => $dhlString('DHL_FREIGHT_PRODUCTS_PATH', '/products'),
            'additional_services' => $dhlString('DHL_FREIGHT_ADDITIONAL_SERVICES_PATH', '/products/{productId}/additionalservices'),
            'additional_services_validation' => $dhlString('DHL_FREIGHT_ADDITIONAL_SERVICES_VALIDATION_PATH', '/products/{productId}/additionalservices/validationresults'),
            'shipments' => $dhlString('DHL_FREIGHT_SHIPMENTS_PATH', '/sendtransportinstruction'),
            'price_quote' => $dhlString('DHL_FREIGHT_PRICE_QUOTE_PATH', '/pricequote/quoteforprice'),
            'label' => $dhlString('DHL_FREIGHT_LABEL_PATH', '/print/printdocumentsbyid'),
            'print_documents' => $dhlString('DHL_FREIGHT_PRINT_DOCUMENTS_PATH', '/print/printdocuments'),
            'print_multiple_documents' => $dhlString('DHL_FREIGHT_PRINT_MULTIPLE_DOCUMENTS_PATH', '/print/printmultipledocuments'),
        ],
        'timeout' => (float) env('DHL_FREIGHT_TIMEOUT', 10.0),
        'connect_timeout' => (float) env('DHL_FREIGHT_CONNECT_TIMEOUT', 5.0),
        'retry' => [
            'times' => (int) env('DHL_FREIGHT_RETRY_TIMES', 3),
            'sleep' => (int) env('DHL_FREIGHT_RETRY_SLEEP', 200),
        ],
        'circuit_breaker' => [
            'failures' => (int) env('DHL_FREIGHT_CIRCUIT_FAILURES', 5),
            'cooldown' => (int) env('DHL_FREIGHT_CIRCUIT_COOLDOWN', 60),
        ],
        'log_channel' => env('DHL_FREIGHT_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'volumetric_weight_factor' => (float) env('DHL_FREIGHT_VOLUMETRIC_WEIGHT_FACTOR', 250.0),
        'ping' => [
            'method' => env('DHL_FREIGHT_PING_METHOD', 'GET'),
            'path' => env('DHL_FREIGHT_PING_PATH', '/health'),
        ],
        'verify' => (bool) env('DHL_FREIGHT_VERIFY_SSL', true),
    ],

    'dhl_push' => [
        'base_url' => env('DHL_PUSH_BASE_URL', 'https://api-test.dhl.com/tracking/push/v1'),
        'api_key' => env('DHL_PUSH_API_KEY', ''),
        'api_key_header' => env('DHL_PUSH_API_KEY_HEADER', 'DHL-API-Key'),
        'paths' => [
            'subscription' => env('DHL_PUSH_SUBSCRIPTION_PATH', '/subscription'),
            'subscription_with_id' => env('DHL_PUSH_SUBSCRIPTION_WITH_ID_PATH', '/subscription/{id}'),
            'subscriptions' => env('DHL_PUSH_SUBSCRIPTIONS_PATH', '/subscriptions'),
        ],
        'timeout' => (float) env('DHL_PUSH_TIMEOUT', 10.0),
        'connect_timeout' => (float) env('DHL_PUSH_CONNECT_TIMEOUT', 5.0),
        'retry' => [
            'times' => (int) env('DHL_PUSH_RETRY_TIMES', 3),
            'sleep' => (int) env('DHL_PUSH_RETRY_SLEEP', 200),
        ],
        'circuit_breaker' => [
            'failures' => (int) env('DHL_PUSH_CIRCUIT_FAILURES', 5),
            'cooldown' => (int) env('DHL_PUSH_CIRCUIT_COOLDOWN', 60),
        ],
        'log_channel' => env('DHL_PUSH_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'verify' => (bool) env('DHL_PUSH_VERIFY_SSL', true),
    ],

    'api' => [
        'key' => env('API_ACCESS_KEY'),
    ],

    'admin_api' => [
        'token' => env('ADMIN_API_TOKEN'),
    ],

    'identity' => [
        'driver' => env('IDENTITY_DRIVER', ''),
        'base_url' => env('IDENTITY_BASE_URL', ''),
        'token' => env('IDENTITY_API_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
