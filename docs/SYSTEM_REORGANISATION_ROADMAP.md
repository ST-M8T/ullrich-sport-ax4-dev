# Systemreorganisation-Roadmap
Stand: 2026-05-11 05:13:07

## 0) Überblick

- Erfasste Routen: `123`
- Erfasste Menüeinträge: `14`
- Ungeschützte Routen: `13`
- Ungeroutete Views: `38`

## 1) Qualitätsgates

| Gate | Name | Status | Besitzer | Risiko |
| --- | --- | --- | --- | --- |
| Gate A | Routing- und Berechtigungsintegrität | erfüllt | Architektur + Backend | keine |
| Gate B | Rollen- und Persona-Konsistenz | erfüllt | Product Owner + Identity | keine |
| Gate C | Navigation ohne tote Verweise | risikobehaftet | Frontend | Menüeinträge ohne wirksames Route-Ziel |
| Gate D | Komplette View-Lifecycle | offen | Frontend + QA | 87+ nicht geroutete Views, 1 kritisch als potenzieller Altbestand |
| Gate E | Viewport- und Interaktionskonformität | offen | UX + Frontend | Automatisierte Breakpoint- und Accessibility-Prüfung noch nicht als Pipeline-Schritt vorhanden |

## 2) Rollenmodell Mitarbeiter / Leiter / Admin

| Persona | Zugeordnete Rollen | Sichtbare Routen | Status |
| --- | --- | ---: | --- |
| Mitarbeiter | operations | 65 | erfüllt |
| Leiter | leiter | 98 | erfüllt |
| Admin | admin | 106 | erfüllt |

## 3) Modul- und Oberflächenübersicht

| Modulfläche | Routen-Anzahl |
| --- | ---: |
| api:log-files | 5 |
| api:system-settings | 5 |
| api:system-status | 1 |
| api:v1 | 9 |
| web:/ | 1 |
| web:configuration | 22 |
| web:csv-export | 4 |
| web:dispatch | 4 |
| web:fulfillment | 49 |
| web:identity | 8 |
| web:login | 2 |
| web:logout | 1 |
| web:logs | 2 |
| web:monitoring | 3 |
| web:setup | 1 |
| web:tracking | 6 |

## 4) Navigation und View-Lifecycle

- Menüpunkte ohne bestehende Route: `0`
- Menüpunkte ohne explizite Berechtigung: `0`
- Menüpunkte mit ungültigem Ziel: `6`

| Kategorie | Anzahl | Beispiel |
| --- | ---: | --- |
| Komponente | 24 | components.filters.filter-form |
| Mail | 2 | mail.domain-event-alert |
| Partial | 11 | configuration.settings.partials.logs.sections.audit-logs |
| Test | 1 | tests.layout-sample |

## 5) Umsetzungspakete

| Paket | Ziel | Owner | Ergebniskriterium |
| --- | --- | --- | --- |
| RP-1 | Leitungsrolle als eigene Persona-Rolle einführen oder fachlich dokumentieren | Product Owner, Identity-Verwaltung | Jede Persona hat eindeutige, dokumentierte Rechtekette im Menü |
| UI-1 | Viewport-/Keyboard/ARIA-Check einführen | Frontend + QA | 360, 768, 1280 Viewports ohne horizontales Overflow und funktionale Navigation |
| FE-1 | Nicht geroutete Views klassifizieren und bereinigen | Frontend + Architektur | Alte Views sind in den Kategorien Produktiv / Wiederverwendung / Archiv mit Ticket verifiziert |
| OP-1 | Reorganisation Audit in Release-Prozess | DevOps + Team | Jede Freigabe enthält neuen Lauf von `system-kartographie-gen.php` + diff Review |

## 6) Sofort-Monitoring vor Deployment

- Sichtbarkeit je Rolle in `docs/SYSTEM_PERMISSION_MATRIX.md` prüfen
- Vollständigkeit Route-Menu-Role in `docs/SYSTEM_ROUTE_VISIBILITY_MATRIX.md` prüfen
- Navigation und ungeroutete Views in `docs/SYSTEM_AUDIT_REPORT.md` prüfen und bei offenen Punkten Tickets anlegen
