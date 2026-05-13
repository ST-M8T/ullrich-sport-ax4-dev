# Feature Index

> Single Source of Truth für alle Feature-Specs. Vor jeder neuen Arbeit hier den Status prüfen.

**Next Available ID:** PROJ-7

## Aktive Initiative — DHL Product Catalog

Vollständiges, datenbankgestütztes Domain-Modell für DHL-Freight-Produkte und Additional Services, automatisch via DHL-API gesynct, mit zentralisiertem Mapping und dynamischer Service-Auswahl im UI.

| ID | Titel | Status | Hängt ab von | Build-Order |
|---|---|---|---|---|
| [PROJ-1](PROJ-1-dhl-catalog-domain-persistence.md) | DHL Catalog — Domain & Persistence | In Review | — | 1 |
| [PROJ-2](PROJ-2-dhl-catalog-sync-job.md) | DHL Catalog — Sync-Job & Alarmierung | In Review | PROJ-1 | 2 |
| [PROJ-6](PROJ-6-dhl-catalog-admin-inspection.md) | DHL Catalog — Read-Only Admin-Inspektion | In Review | PROJ-1, PROJ-2 | 3 |
| [PROJ-3](PROJ-3-dhl-additional-service-mapper.md) | DHL Additional Service — Mapper-Zentralisierung | In Review | PROJ-1, PROJ-2 | 4 |
| [PROJ-4](PROJ-4-freight-profile-catalog-fk.md) | Versandprofil — Migration auf Katalog-FK | In Review | PROJ-1, PROJ-2, PROJ-3 | 5 |
| [PROJ-5](PROJ-5-dhl-booking-dynamic-services.md) | DHL-Buchungsformular — Dynamische Service-UI | In Review | PROJ-1, PROJ-3, PROJ-4 | 6 |

## Statuslegende
- **Planned** — Spec geschrieben, noch nicht in Architektur
- **In Progress** — Architektur/Implementierung läuft
- **In Review** — PR offen, QA läuft
- **Deployed** — In Produktion live
