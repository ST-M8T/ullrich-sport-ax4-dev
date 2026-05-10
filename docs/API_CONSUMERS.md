# API-Surfaces & Konsumenten

> Dokumentation aller HTTP-API-Endpunkte, ihrer Auth-Mechanik und der bekannten Konsumenten.
> Pflegen bei jedem neuen API-Endpunkt oder Auth-Wechsel.

Stand: 2026-05-08

---

## 1) Surfaces im Überblick

Die Anwendung exponiert **drei** unterschiedliche API-Surfaces, jede mit eigenem Auth-Modell:

| Surface | Pfad | Auth-Mechanismus | Konsumenten-Klasse |
|---|---|---|---|
| **Public-Health** | `/v1/health/*` | keine (öffentlich) | Loadbalancer, Uptime-Monitoring (Pingdom o.ä.) |
| **Public-API (key-auth)** | `/v1/...` | `X-API-Key`-Header oder `?api_key=`-Query | externe Clients mit `services.api.key`-Token |
| **Admin-API (token-auth)** | `/admin/...` | `X-Admin-Token`-Header oder `Authorization: Bearer`-Header | Operations / DevOps |

**Middleware-Group `api`** wickelt Public-API + Public-Health ein und appendet:
- `throttle:secure-api` (Rate-Limit)
- `AuthenticateApiKey` (key-auth, gibt `null` durch wenn `services.api.key` leer ist — Health-Checks daher offen)
- `RecordRequestMetrics`
- `EnforceSecurityHeaders`

**Admin-API** zusätzlich `EnsureAdminApiAuthenticated` per Route-Middleware-Alias `auth.admin`.

---

## 2) Public-Health-Endpunkte (offen)

| Methode | URI | Zweck |
|---|---|---|
| GET | `/v1/health/live` | Liveness-Probe (immer 200 wenn der Prozess läuft) |
| GET | `/v1/health/ready` | Readiness-Probe (DB, Cache, Queue erreichbar) |

**Nutzer**: Loadbalancer, Container-Orchestrator, Uptime-Monitoring.

---

## 3) Public-API (X-API-Key)

Alle Endpunkte sind effektiv mit `X-API-Key` geschützt, **sobald** `services.api.key` (in `.env` oder `system_settings`) gesetzt ist. Wenn der Key leer ist, sind die Endpunkte offen — daher: **in Production muss der Key gesetzt sein**.

| Methode | URI | Zweck | Bekannte Konsumenten |
|---|---|---|---|
| GET | `/v1/dispatch-lists` | Listet aktuell offene Dispatch-Listen | TODO: zu klären |
| GET | `/v1/dispatch-lists/{list}/scans` | Scan-History einer Liste | mobile Scan-App (Hinweis aus `DispatchScanController`) |
| POST | `/v1/dispatch-lists/{list}/scans` | Scan erfassen | mobile Scan-App |
| GET | `/v1/shipments/{trackingNumber}` | Shipment-Status per Tracking-Nr | externer Tracker / Kundeportal |
| GET | `/v1/tracking-jobs` | Tracking-Job-Listing | Operations-Dashboard |
| GET | `/v1/tracking-alerts` | Tracking-Alerts-Listing | Operations-Dashboard |
| GET | `/v1/settings/{key}` | Lese-Zugriff auf einzelne Settings | externe Read-Only-Konsumenten |

**Empfehlung**: Konsumenten-Liste in der Tabelle bei jedem Onboarding eines neuen Clients ergänzen. Health-Checks NICHT verändern.

---

## 4) Admin-API (Token)

Alle Routes unter `/admin/`-Prefix in `routes/api.php`. Auth via `auth.admin`-Middleware (`EnsureAdminApiAuthenticated`), die einen Bearer-Token akzeptiert.

| Methode | URI | Zweck |
|---|---|---|
| GET | `/admin/system-status` | System-Status-Aggregat (ähnlich Web-Setup-View) |
| GET | `/admin/system-settings` | Listet alle Settings |
| POST | `/admin/system-settings` | Setting anlegen |
| GET | `/admin/system-settings/{settingKey}` | Single-Setting lesen |
| PATCH | `/admin/system-settings/{settingKey}` | Setting updaten |
| DELETE | `/admin/system-settings/{settingKey}` | Setting löschen |
| GET | `/admin/log-files` | Listet verfügbare Log-Files |
| GET | `/admin/log-files/{file}` | Einzelnes Log-File-Meta |
| GET | `/admin/log-files/{file}/entries` | Tail-Inhalt des Log-Files |
| POST | `/admin/log-files/{file}/actions/download` | Download-Trigger |
| DELETE | `/admin/log-files/{file}` | Log-File löschen |

**Konsumenten**: Operations-Tooling, DevOps-Pipelines, Setup-Skripte.

**Empfehlung**: Admin-Token getrennt vom API-Key halten. Beide regelmäßig rotieren (siehe `SecretRotationService` für `system_secret_versions`).

---

## 5) Vertragsregeln

1. **Versionierung**: Public-API ist `/v1/`. Bei Breaking Change → `/v2/`-Surface anlegen, NICHT bestehende `/v1/`-Pfade ändern.
2. **Idempotenz**: Schreibende Endpoints (`POST /v1/dispatch-lists/{list}/scans`, `POST /admin/system-settings`) müssen idempotent verarbeitet werden. Engineering-Handbuch §24.
3. **Antwort-Format**: JSON. Bei Auth-Failure: `{"message": "Unauthorized"}` mit Status 401.
4. **Rate-Limit**: `secure-api` (siehe `AppServiceProvider::configureRateLimiting`). Default 120 req/min, konfigurierbar via `security.rate_limiting.api.*`.
5. **Logging**: alle API-Calls werden via `RecordRequestMetrics` instrumentiert.
6. **Security-Header**: `EnforceSecurityHeaders` setzt `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Strict-Transport-Security`.

---

## 6) Offene Punkte

- **Konsumenten-Liste** ist nur teilweise gefüllt — Operations sollte die produktiv genutzten Clients per Issue mitteilen.
- **OpenAPI-Spec**: aktuell keine generierte Spec. `/docs/`-Ordner enthält DHL-/Plenty-Yamls, aber keine eigene. Folgesticket: B-2/ARCH-4-Continuation.
- **Token-Rotation**: Standard-Prozess für Admin-Token-Rotation noch nicht dokumentiert. SecretRotationService existiert für Settings, aber nicht für Admin-API-Token. Folgesticket.

---

## 7) Quellen

- `routes/api.php` (Definition)
- `bootstrap/app.php` (Group-Middleware)
- `app/Http/Middleware/AuthenticateApiKey.php`
- `app/Http/Middleware/EnsureAdminApiAuthenticated.php`
- `app/Providers/AppServiceProvider.php` (Rate-Limit-Config)
- `docs/SYSTEM_ROUTE_KARTOGRAPHIE.md` (vollständige Routen-Liste)
