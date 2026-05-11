# Cross-Role Permission Leak Detection Report
Stand: 2026-05-10

## Konsolidierte Ergebnisse der 3 Persona-Walkthroughs

| Persona | Menupunkte sichtbar | Permissions | Hidden-but-Reachable | 403-Lecks | API-Lecks |
|---|---|---|---|---|---|
| Operations | 3 (Logs, Monitoring) | ~9 | 0 | 0 | 0 |
| Leiter | 8 | 17 | 3 (Mail-Vorlagen, Integrationen, System-Settings) | 0 | 0 |
| Admin | 8 | wildcard `*` | 0 | 0 (Past Write-Escalation FIXED) | 1 (public settings disclosure) |

---

## P0 — SECURITY CRITICAL

### 1. `GET /api/v1/settings/{key}` — Public Info Disclosure

| Feld | Wert |
|---|---|
| Severity | P0 CRITICAL |
| Route | `/api/v1/settings/{key}` |
| Methode | GET |
| Auth | NONE (ungeschützt) |
| Berechtigung | — |
| Rollen | keine (public) |
| Problem | Exponiert system-level configuration values (DHL credentials, API keys, system secrets stored as settings). Anyone with API key can read any setting by key. Documented in API_CONSUMERS.md as "externe Read-Only-Konsumenten" but without any authentication layer. |

**Risiko:** Settings mit Key `dhl.api.key`, `services.api.key`, `mail.smtp.password`, etc. sind ohne Session-Auth abrufbar.

**Fix-Priorität:** SOFORT. Endpoint muss entweder:
- `auth.admin` Middleware require (Bearer Token), ODER
- `AuthenticateApiKey` + Mapping auf erlaubte public-Keys einschränken, ODER
- Setting-Key-Whitelist fuer publicLesen definieren.

---

## P1 — HIGH (Hidden-but-Reachable ohne Menüeintrag)

### 2. Leiter: Mail-Vorlagen Konfiguration

| Feld | Wert |
|---|---|
| Severity | P1 HIGH |
| Route(s) | 7 routes unter `/admin/configuration/mail-templates/*` |
| Route-Namen | `configuration-mail-templates`, `configuration-mail-templates.store`, `configuration-mail-templates.create`, `configuration-mail-templates.destroy`, `configuration-mail-templates.update`, `configuration-mail-templates.edit`, `configuration-mail-templates.preview` |
| Permission | `admin.access` + `configuration.mail_templates.manage` |
| Menu fuer Leiter | KEINER (nur admin + configuration sehen es) |
| Problem | Leiter hat `configuration.mail_templates.manage` Permission (laut SYSTEM_PERMISSION_MATRIX) UND `admin.access`. Die 7 Routen sind fuer Leiter via URL erreichbar aber nicht im Menu sichtbar. Dies ist eine Menu-Visibility-Inkonsistenz, kein echtes Permission Leak (Leiter darf technisch). |

**Tatsaechliche Gefahr:** Gering — Leiter darf diese Konfiguration fachlich. Problem ist Menu-Fehl.

**Fix:** Menu-Eintrag für Leiter hinzufuegen (z.B. "Konfiguration > Mail-Vorlagen") ODER Explicit dokumentieren dass Leiter keine Mail-Vorlagen sehen darf und Berechtigung entziehen.

---

### 3. Leiter: Integrationen Konfiguration

| Feld | Wert |
|---|---|
| Severity | P1 HIGH |
| Route(s) | 4 routes unter `/admin/configuration/integrations/*` |
| Route-Namen | `configuration-integrations`, `configuration-integrations.show`, `configuration-integrations.update`, `configuration-integrations.test` |
| Permission | `admin.access` + `configuration.integrations.manage` |
| Menu fuer Leiter | KEINER |
| Problem | Gleiche Situation wie Mail-Vorlagen. Leiter hat Permission aber kein Menu. |

---

### 4. Leiter: System-Settings

| Feld | Wert |
|---|---|
| Severity | P1 HIGH |
| Route(s) | 11 routes unter `/admin/configuration/settings/*` |
| Route-Namen | `configuration-settings`, `configuration-settings.store`, `configuration-settings.create`, `configuration-settings.group-update`, `configuration-settings.update`, `configuration-settings.edit` |
| Permission | `admin.access` + `configuration.settings.manage` |
| Menu fuer Leiter | KEINER (nur admin + configuration haben Menu) |
| Problem | Leiter hat `configuration.settings.manage` Permission (SYSTEM_PERMISSION_MATRIX zeigt 29 sichtbare Routen fuer configuration Rolle) aber kein Menu. Die Web-Routen sind fuer Leiter explizit via can:-Middleware abgesichert. Kein 403-Leck aber Menu-Inkonsistenz. |

**Korrektur:** Wenn Leiter diese Routen laut Permission Matrix darf, Menu-Eintrag ergaenzen. Wenn nicht, Permission entziehen.

---

## P2 — MEDIUM

### 5. Ungeschuetzte API-Endpunkte (Intentional aber undokumentiert)

| URI | Auth | Dokumentiert in API_CONSUMERS | Problem |
|---|---|---|---|
| `/v1/dispatch-lists` | API-Key (conditional) | Ja | Offen wenn `services.api.key` nicht gesetzt |
| `/v1/dispatch-lists/{list}/scans` (GET/POST) | API-Key (conditional) | Ja | Offen wenn Key leer |
| `/v1/shipments/{trackingNumber}` | API-Key (conditional) | Ja | Tracking data公开 |
| `/v1/tracking-jobs` | API-Key (conditional) | Ja | Job list公开 |
| `/v1/tracking-alerts` | API-Key (conditional) | Ja | Alert list公开 |
| `/v1/settings/{key}` | NONE | Ja, aber Key-Pflicht nicht erzwungen | **KRITISCH** (siehe P0) |

**Problem:** Die API_CONSUMERS.md sagt "Sobalald services.api.key gesetzt ist, sind Endpunkte geschuetzt" — aber der `AuthenticateApiKey`-Middleware laesst Requests durch wenn Key leer ist. Das bedeutet: in Dev/Stage ohne Key = alles offen.

**Empfehlung:** Defensive Architektur — wenn Key nicht konfiguriert, sollten Endpunkte 401 zurueckgeben statt offen zu sein.

---

### 6. Menu-Rolle Diskrepanz (Alle nicht-admin Rollen)

Aus SYSTEM_ROUTE_VISIBILITY_MATRIX: Alle Rollen (operations, support, configuration, identity, viewer) sehen die **gleichen 8 Menupunkte**:
- Monitoring · system-status
- Monitoring · system-jobs
- Monitoring · tracking
- Verwaltung · identity-users
- Verwaltung · notifications
- Logs · system-logs
- Logs · audit-logs
- Logs · domain-events

**Problem:** Die Route-sichtbarkeit ist sehr unterschiedlich (operations: 65 Routen, viewer: 13 Routen), aber das Menu ist identisch. Benutzer sehen Menu-Eintraege fuer Bereiche, zu denen sie keine Routen-Zugriff haben (z.B. viewer sieht "Verwaltung > identity-users" Menu aber hat nur 13 Routen).

Dies fuehrt zu "Menu exists but click leads to 403" fuer viewer/operations bei verschiedenen Bereichen.

---

## Verbliebene Risiken ausserhalb Permission-Matrix

### 7. PaginatorLinkGenerator — Domain → Framework Coupling

| Feld | Wert |
|---|---|
| Datei | `app/Domain/Shared/ValueObjects/Pagination/PaginatorLinkGenerator.php` |
| Problem | Domain-Layer importiert `Illuminate\Contracts\Pagination\LengthAwarePaginator` und `Illuminate\Pagination\LengthAwarePaginator`. Domain kennt Framework-Klassen. Verletzt Engineering-Handbuch §4 (Domain darf nichts aus Infrastructure/Presentation wissen) und §9 (Framework darf Domain nicht dominieren). |
| Severity | ARCHITECTURAL (kein Security-Risk, aber违反 Solid/DDD) |
| Umfang | 1 Klasse, 98 LoC, verwendet in View Composers und Resources |

**Konsequenz:** Domain ist nicht Framework-unabhaengig testbar. Wenn Laravel ausgetauscht wird, muss Domain angefasst werden.

---

### 8. Fehlende Design Tokens

| Problem | Umfang |
|---|---|
| `--space-*` CSS Custom Properties fehlen | Nicht bekannt |
| `--font-size-*` CSS Custom Properties fehlen | Nicht bekannt |
| Magic Numbers in CSS | Nicht bekannt wie im Audit dokumentiert |

Fuer UX-Audit Team: Dies ist P3 fuer Security, aber P1 fuer Code Quality.

---

### 9. Accessibility: tabs aria-selected

| Ort | Problem |
|---|---|
| `resources/views/components/tabs.blade.php` (oder aehnlich) | Nicht geprueft ob `aria-selected` korrekt gesetzt wird |
| `resources/views/components/sidebar-tabs.blade.php` | Nicht geprueft |

Sollte in t20 UI Accessibility Audit addressiert werden.

---

### 10. Focus-Trap in Modals

| Ort | Problem |
|---|---|
| `monitoring.partials.modal` (3x verwendet) | Nicht geprueft ob Focus-Trap implementiert |

Sollte in t20 UI Accessibility Audit addressiert werden.

---

## Priorisierte Fix-Liste

| # | Severity | Issue | Action |
|---|---|---|---|
| 1 | P0 | `GET /api/v1/settings/{key}` public info disclosure | Middleware `auth.admin` hinzufuegen oder API-Key Pflicht durchsetzen |
| 2 | P1 | Leiter: 3 Konfigurations-Routen ohne Menu (Mail-Vorlagen, Integrationen, System-Settings) | Menu fuer Leiter ergaenzen ODER Permissions bereinigen |
| 3 | P2 | Conditional API-Auth (Key leer = alles offen) | Defensive Fallback: ohne Key → 401 statt durchlassen |
| 4 | P2 | Menu identisch fuer alle Rollen trotz unterschiedlicher Berechtigungen | Menu dynamisch je nach Rolle filtern |
| 5 | ARCH | PaginatorLinkGenerator Domain → Framework | Strategy: Paginator aus Domain extrahieren, Interface in Domain, Implementation in Infrastructure |
| 6 | P3 | fehlende Design Tokens, aria-selected, focus-trap | In t20 UI Accessibility Audit addressieren |

---

## Summary

| Severity | Count |
|---|---|
| P0 CRITICAL | 1 |
| P1 HIGH | 3 (Leiter hidden-but-reachable) |
| P2 MEDIUM | 2 |
| ARCHITECTURAL | 1 |
| P3 LOW | 2-3 |

**Axes:** Die Permission-Lecks sind primär Menu-Routing-Inkonsistenzen (Leiter darf technisch, Menu zeigt es nicht) und ein echter public-Endpoint der Settings exposes. Keine horizontale Privilege-Escalation (kein Admin-Schreiben durch Leiter) gefunden. Past Write-Escalation (api/admin/*) wurde bereits gefixt.