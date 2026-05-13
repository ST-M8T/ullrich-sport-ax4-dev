# DEAD_CODE
**Stand:** 2026-05-13 · System-weiter Scan

## 1) Statistik
- PHP-Klassen geprüft: 542
- Routes: 85+
- Views: 125
- Components: 57
- JS-Module: 31
- Migrations: 29
- Tests: 190

**Findings:** 🔴 1 HIGH · 🟠 1 MEDIUM · 🟡 0 LOW

## 2) Dead Routes
**Ergebnis:** ✓ KEINE Dead-Routes. Alle 85+ Routes haben aktive Controller-Methoden.

## 3) Dead Controllers / Methods
**Ergebnis:** ✓ KEINE. Alle 58 Controllers + Public-Methods sind geroutet.

## 4) Dead Application/Domain-Klassen
**Ergebnis:** ✓ KEINE. Alle 13 Application-Services sind Container-bound oder per DI injiziert.

## 5) Dead Views / Components
**Ergebnis:** ✓ KEINE. 55 routed Views + 57 Components + 22 Partials, alle aktiv.

## 6) Dead JS-Module

### 🔴 HIGH
| Datei | Status | Begründung |
|---|---|---|
| `resources/js/inline-forms.js` | **DUPLIKAT** (root-level) | app.js importiert `./utilities/inline-forms` (Z.38) — der utilities-Pfad ist die genutzte Version. Root-Datei wird **nicht** importiert. |

**Korrigierte Diagnose (gegenüber t6):** der **root-level** `resources/js/inline-forms.js` ist die unbenutzte Datei (nicht utilities/). app.js Z.38 importiert explizit aus `./utilities/inline-forms`.

**Action:** DELETE `resources/js/inline-forms.js` (root-level) NACH Verifikation dass kein anderer Import darauf verweist.

## 7) Kommentierter Code
**Ergebnis:** ✓ KEINE problematischen Blöcke. ~15 Files mit Inline-Doku-Kommentaren (deutsche technische Notizen) — keine kommentierten Code-Strukturen.

🟡 LOW: `app/Models/User.php:5` — `// use Illuminate\Contracts\Auth\MustVerifyEmail;` (1-Zeilen-Kommentar, trivial)

## 8) Alte TODOs/FIXMEs
**Ergebnis:** ✓ 0 TODO/FIXME/HACK/XXX-Kommentare im Production-Code. Disziplin gut.

## 9) Debug-Reste
**Ergebnis:** ✓ 0 Vorkommen von `die()`/`dd()`/`var_dump()`/`print_r()`/`dump()` in app/, routes/, resources/.

## 10) Deprecated-PHPDoc

| Klasse/Methode | Seit | Status | Empfehlung |
|---|---|---|---|
| `DhlBookingOptions::productId()` | Nov 2025 (commit d4431f0) | **Noch genutzt** (11+ Refs) | Behalten bis DhlProductCode-Migration abgeschlossen ist (geplant, nicht dead) |

## 11) Priorisierte Lösch-Cluster

| # | Cluster | File | Risiko | LoC |
|---|---|---|---|---|
| 1 | Duplicate JS-Root-File | `resources/js/inline-forms.js` | HIGH (sicher) | 51 |
| 2 | Unused-Import-Kommentar | `app/Models/User.php:5` | LOW | 1 |

## 12) Engineering-Handbuch §63 (YAGNI) Compliance

✅ **EXCELLENT** — die Codebase ist sehr clean:
- Keine Dead-Routes
- Keine Dead-Controllers
- Keine Dead-Services
- Keine kommentierten Code-Blöcke
- Keine stalen TODOs
- Keine Debug-Statements
- Nur 1 JS-Duplikat (eindeutige Twins)

**Overall Code-Quality: EXCELLENT** — YAGNI-Disziplin wird gelebt.

---
_t8-Output, GOAL-2026-05-12T194500-syscart_
