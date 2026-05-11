# AX4 Admin Platform (Development)

This repository contains the development environment for the AX4 admin platform.  
It is a Laravel 12 application that orchestrates identity management, fulfillment, dispatch, tracking, and monitoring workflows.  
The project now ships with a comprehensive automated testing stack (unit, feature, browser) and a preconfigured CI pipeline to keep quality gates enforceable.

## Requirements

- PHP 8.2+
- Composer 2.6+
- Node.js 18+ and npm
- SQLite (built-in) or alternative database supported by Laravel
- Chrome/Chromedriver for Dusk browser tests

## Getting Started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Create and migrate a local database (uses sqlite memory by default)
php artisan migrate

# Optional demo data for UI smoke testing
php artisan db:seed --class=Database\\Seeders\\DomainDemoSeeder

npm run build   # or `npm run dev` during local development
php artisan serve
```

## Sample Data & Factories

- `Database\Seeders\DomainDemoSeeder` provisions representative data for fulfillment, dispatch, tracking, and monitoring domains.
- Rich model factories live in `database/factories/*` to quickly compose test fixtures.
- Test helpers in `tests/Support/CreatesDomainData.php` wrap common setup routines for feature and integration tests.

## Quality Gates

| Area                | Command                                  | Notes |
|---------------------|-------------------------------------------|-------|
| Unit & Feature      | `php artisan test`                       | Runs the PHPUnit suite (Feature + Unit) |
| Pest Runner         | `vendor/bin/pest`                        | Alternative runner with granular reporting |
| Browser (Dusk)      | `php artisan dusk --env=dusk`            | Requires Chrome/Chromedriver; uses `phpunit.dusk.xml` |
| Static Analysis     | `vendor/bin/phpstan analyse`             | Larastan configured via `phpstan.neon` — **Level 8** (PR-blocking). Hochstufung auf Level 9 im Backlog. |
| Code Style          | `vendor/bin/pint --test`                 | Non-destructive check; auto-fix mit `vendor/bin/pint`. Aktuell: clean. |
| System Cartography  | `php scripts/system-kartographie-gen.php --project-root=. --output-dir=docs` | Generiert `docs/SYSTEM_*.md` aus Routen/Views/Composers. CI prüft Drift. |
| A11y Smoke          | `npm run audit:a11y`                     | axe-core CLI gegen lokalen `php artisan serve`. WCAG 2.1 AA. |
| Complete QA bundle  | `composer qa`                            | Runs tests, Pest, PHPStan, and Pint in sequence |

Convenience scripts are available in `composer.json` (`composer test`, `composer dusk`, `composer qa`).

### Verbindliche Dokumentation

- [`docs/SYSTEM_CLEANUP_BACKLOG.md`](docs/SYSTEM_CLEANUP_BACKLOG.md) — laufendes Cleanup-Backlog (Single Source of Truth)
- [`docs/UI_COMPONENT_REFERENCE.md`](docs/UI_COMPONENT_REFERENCE.md) — Komponenten-Bibliothek (verbindlich vor jedem neuen UI)
- [`docs/UX_GUIDELINES.md`](docs/UX_GUIDELINES.md) — UX-/A11y-/Layout-Vorgaben
- [`docs/API_CONSUMERS.md`](docs/API_CONSUMERS.md) — API-Surfaces, Konsumenten, Auth-Mechanismus
- [`docs/openapi.yaml`](docs/openapi.yaml) — OpenAPI-3.1-Spec der Public-API
- [`docs/SERVICE_INVENTORY.md`](docs/SERVICE_INVENTORY.md) — Application-Services-Inventar
- Generierte Audit-Berichte: `docs/SYSTEM_AUDIT_REPORT.md`, `docs/SYSTEM_PERMISSION_MATRIX.md`, `docs/SYSTEM_ROUTE_KARTOGRAPHIE.md`, `docs/SYSTEM_VIEW_KARTOGRAPHIE.md`, `docs/SYSTEM_MENU_ROLE_MATRIX.md`, `docs/SYSTEM_ROUTE_VISIBILITY_MATRIX.md`, `docs/SYSTEM_REORGANISATION_ROADMAP.md`

## Domain Events & Queues

- Domain events recorded through `DomainEventService` are written to `domain_events` and projected into the `reporting_*` read-model tables by the queued `ProcessDomainEvent` job. Queue routing, retry, and backoff behaviour can be tuned via `config/domain-events.php` and `.env` keys such as `DOMAIN_EVENTS_QUEUE`, `DOMAIN_EVENTS_QUEUE_BACKOFF`, etc.
- Follow-up processing (notifications, monitoring hooks) is captured by `DispatchDomainEventFollowUp` jobs which log outcomes in `system_jobs` and emit StatsD counters. Horizon supervisors for `domain-events` and `monitoring` queues are pre-configured in `config/horizon.php` and may be adjusted per environment.

## Dispatch API Notes

- `/api/v1/dispatch-lists` liefert standardmäßig nur Metadaten & `scan_count`. Scans können bei Bedarf mit `?include=scans` dazugeladen werden; jedes Element liefert dann `scans` in ISO-8601-Zeitstempeln (`DispatchScanResource`).
- `/api/v1/dispatch-lists/{list}/scans` bietet denselben Scan-Payload isoliert; geeignet für Clients, die ausschließlich Scans anfordern.
- Administrator-UI nutzt dieselben JSON-Endpunkte für modale Scantabellen (Vite-Bundle `resources/js/pages/dispatch-scans-modal.js`).
- Sequenzen werden über `dispatch_sequences` verwaltet; die Down-Migration erzeugt Platzhalter-Zeilen und restauriert Auto-Increment-Werte, legt jedoch keine historischen Daten wieder an.

## Integration Health Checks

- `php artisan plenty:ping` — executes a resilience-aware connectivity check against the Plenty REST gateway (timeouts, retry, circuit breaker, logging). Results are persisted to `system_jobs` for auditability. Use `--show-body` to print the response payload.
- `php artisan dhl:ping` — mirrors the Plenty check for the DHL tracking gateway and records success/failure metrics in the same way.

## DHL Freight API Setup

- Configure the sandbox credentials from the DHL developer portal in `.env`: `DHL_AUTH_BASE_URL`, `DHL_AUTH_USERNAME`, `DHL_AUTH_PASSWORD`, `DHL_FREIGHT_BASE_URL`, `DHL_FREIGHT_API_KEY`, `DHL_FREIGHT_API_SECRET`, `DHL_FREIGHT_AUTH`.
- Optional tuning via `DHL_FREIGHT_TIMEOUT`, `DHL_FREIGHT_CONNECT_TIMEOUT`, retry/circuit breaker keys, and the path overrides (`DHL_FREIGHT_*_PATH`) bundled in `config/services.php`.
- Bearer tokens come from the DHL Auth API (`DHL_AUTH_*`), cached via `DHL_AUTH_TOKEN_CACHE_TTL`; push webhooks use `DHL_PUSH_*` keys.
- The gateway is available via `App\Domain\Integrations\Contracts\DhlFreightGateway` and ships helpers for timetable (POST), price quotes, product/additional-service catalogs with validation, shipment booking, label/print variants, and a health check.

## Continuous Integration

GitHub Actions workflow (`.github/workflows/ci.yml`) executes on every push and pull request:

1. Installs PHP 8.2 & Node 18 dependencies.
2. Builds Vite assets.
3. Runs PHPUnit, Pest, PHPStan (Larastan), and Laravel Pint.
4. Executes Laravel Dusk browser tests in headless Chrome.

Status checks will fail if any QA stage fails.

## Browser Testing Notes

- `phpunit.dusk.xml` configures a dedicated sqlite database at `database/dusk.sqlite`.
- Ensure Chromedriver matches the locally installed Chrome version.
- Run Dusk locally with:

  ```bash
  php artisan dusk --env=dusk
  ```

  or via composer: `composer dusk`.

## Contributing

- Follow the steps in [CONTRIBUTING.md](CONTRIBUTING.md) for branch naming, commit hygiene, and QA expectations.
- Pull requests must pass the full QA suite (`composer qa`) before submission.
- Significant UI changes should be accompanied by Dusk coverage to assert end-to-end behaviour.

## License

This project is open-sourced under the [MIT license](https://opensource.org/licenses/MIT).
