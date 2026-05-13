<?php

declare(strict_types=1);

/**
 * DHL Catalog configuration (PROJ-1/2/3/4).
 *
 * Engineering-Handbuch §32 (Konfigurationsregel): All catalog-tuning values
 * live here — no magic values in domain/application code. The defaults are
 * the conservative baseline; per-environment overrides go via env().
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Countries
    |--------------------------------------------------------------------------
    |
    | ISO-3166-1 alpha-2 country codes (CSV in env, normalized to a list of
    | uppercase strings here). Used as the default routing perimeter for sync
    | and validation when no explicit country list is provided by the caller.
    |
    */
    'default_countries' => array_values(array_filter(array_map(
        static fn (string $code): string => strtoupper(trim($code)),
        explode(',', (string) env('DHL_CATALOG_COUNTRIES', 'DE,AT,FR,NL,BE,PL,CH'))
    ), static fn (string $code): bool => $code !== '')),

    /*
    |--------------------------------------------------------------------------
    | Default Payer Codes
    |--------------------------------------------------------------------------
    |
    | Pre/post-carriage payer codes (see DhlPayerCode). DAP and DDP cover the
    | overwhelming majority of bookings — additional codes can be enabled via
    | sync_status or manual seeding.
    |
    */
    'default_payer_codes' => ['DAP', 'DDP'],

    /*
    |--------------------------------------------------------------------------
    | Retention Windows (days)
    |--------------------------------------------------------------------------
    */
    'sync_retention_days' => (int) env('DHL_CATALOG_SYNC_RETENTION_DAYS', 365),
    'audit_retention_days' => (int) env('DHL_CATALOG_AUDIT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Strict Validation
    |--------------------------------------------------------------------------
    |
    | When `true`, application services reject bookings whose product/service
    | combination is not present in the catalog. Activated by PROJ-3/4 with
    | the catalog fully seeded — masterdata FormRequest validation always
    | enforces product/parameter existence regardless of this flag.
    |
    */
    'strict_validation' => (bool) env('DHL_CATALOG_STRICT_VALIDATION', true),

    /*
    |--------------------------------------------------------------------------
    | Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of mail addresses notified when the sync enters
    | a failure streak (idempotency-guarded by `mail_sent_for_failure_streak`).
    |
    */
    'alert_recipients' => array_values(array_filter(array_map(
        static fn (string $address): string => trim($address),
        explode(',', (string) env('DHL_CATALOG_ALERT_RECIPIENTS', ''))
    ), static fn (string $address): bool => $address !== '')),

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | Cron expression for the weekly catalog sync. Default: Sunday 03:00.
    |
    */
    'schedule_cron' => (string) env('DHL_CATALOG_SCHEDULE_CRON', '0 3 * * 0'),

];
