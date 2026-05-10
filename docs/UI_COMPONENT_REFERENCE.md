# UI-Komponenten-Bibliothek

> **Quelle**: `resources/views/components/`. Diese Datei dokumentiert alle 23 anonymen
> Blade-Komponenten, ihre Props/Slots und kanonische Nutzung. Engineering-Handbuch §35–§39.
>
> Bei jeder Erweiterung: Komponente hier dokumentieren oder bestehende Komponente nutzen.
> shadcn-/Bootstrap-Pattern: vor jeder neuen UI-Struktur **zuerst** in dieser Liste nachsehen.

Stand: 2026-05-08

---

## 1. Layout-Bausteine

### `<x-ui.page-header>` — Seiten-Header

Verkapselt den 18× wiederkehrenden Cluster `d-flex justify-content-between align-items-center mb-4`
mit Titel und optionalen Aktionen.

| Prop | Typ | Default | Zweck |
|---|---|---|---|
| `title` | `string\|null` | `null` | H1-Text. Wenn gesetzt UND `$slot` leer → Standard-Header. |
| `subtitle` | `string\|null` | `null` | Optionale Untertitel-Zeile. |

**Slots**:
- `default` — Custom-Header-Inhalt (überschreibt `title`/`subtitle`).
- `actions` — rechts platzierte Action-Buttons.

**Beispiele**:
```blade
{{-- Einfach --}}
<x-ui.page-header title="Benutzerverwaltung">
    <x-slot:actions>
        <a href="{{ route('identity-users.create') }}" class="btn btn-primary">Neuen Benutzer anlegen</a>
    </x-slot:actions>
</x-ui.page-header>

{{-- Custom (mit dynamischem Untertitel) --}}
<x-ui.page-header>
    <h1 class="mb-1">Benutzer: {{ $user->username() }}</h1>
    <p class="text-muted mb-0">ID #{{ $user->id()->toInt() }}</p>
    <x-slot:actions>...</x-slot:actions>
</x-ui.page-header>
```

### `<x-ui.section-header>` — Abschnitts-Header

Verkapselt den Section-Header-Cluster `d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3`
mit Titel, Count-Badge und optionalen Aktionen.

| Prop | Typ | Default | Zweck |
|---|---|---|---|
| `title` | `string\|null` | `null` | H2-Text (Style: `h5`). |
| `description` | `string\|null` | `null` | Untertitel-Zeile. |
| `count` | `int\|null` | `null` | Optional: zeigt Count als Bootstrap-Badge neben Title. |

**Slots**: `actions` (rechts), `default` (Custom-Inhalt).

```blade
<x-ui.section-header
    title="Verpackungen"
    description="Transportprofile mit Maßen, Slots und Stack-Informationen."
    :count="$count">
    <x-slot:actions>
        <x-ui.action-link :href="$packagingListUrl">Vollständige Liste</x-ui.action-link>
    </x-slot:actions>
</x-ui.section-header>
```

### `<x-ui.action-link>` — Aktion-Link / -Button

Konsolidiert den 7× wiederkehrenden Cluster `btn btn-outline-primary btn-sm text-uppercase`.

| Prop | Typ | Default | Zweck |
|---|---|---|---|
| `href` | `string\|null` | `null` | Wenn gesetzt → `<a>`, sonst `<button>`. |
| `variant` | `string` | `'outline-primary'` | Bootstrap-Button-Variant (`primary`, `outline-secondary`, ...) |
| `size` | `string\|null` | `'sm'` | `'sm'`, `'lg'` oder leer. |

```blade
<x-ui.action-link :href="$senderListUrl">Vollständige Liste</x-ui.action-link>
<x-ui.action-link variant="outline-secondary">Schließen</x-ui.action-link>
```

### `<x-ui.action-card>` — Akzent-Card mit Aktion

| Prop | Typ | Default | Zweck |
|---|---|---|---|
| `title` | `string` | `'Aktionen'` | Card-Header-Text. |

**Slots**: `default` (Inhalt), `actions` (rechts vom Header).

### `<x-ui.info-card>` — Information-Card

Schmale Karte für Info-Blöcke. Slot: `default`.

### `<x-ui.empty-state>` — Empty-State-Marker

| Prop | Typ | Default | Zweck |
|---|---|---|---|
| `title` | `string` | (Pflicht) | Headline. |
| `description` | `string` | `''` | Detail-Text. |
| `actions` | `array` | `[]` | `[['label', 'style', 'url']]` für CTA-Buttons. |

```blade
<x-ui.empty-state
    title="Keine Daten"
    description="..."
    :actions="[['label' => 'Anlegen', 'style' => 'primary', 'url' => route('foo.create')]]"
/>
```

### `<x-ui.spinner>` — Lade-Indikator

| Prop | Default | Zweck |
|---|---|---|
| `message` | `'Lade...'` | Hinweistext. |

### `<x-ui.breadcrumbs>` — Brotkrumen-Navigation

Wird i.d.R. über das Layout gesetzt; eigenständige Nutzung selten.

### `<x-ui.definition-list>` — Definitions-Liste (dt/dd)

| Prop | Typ | Default | Zweck |
|---|---|---|---|
| `items` | `array` | `[]` | `[['label', 'value']]`. `value` darf HTML enthalten. |

---

## 2. Daten-Tabellen

### `<x-ui.data-table>` — Daten-Tabelle

Konsolidiert die ≥20 Tabellen-Cluster (`table align-middle mb-0` + Varianten).

| Prop | Typ | Default | Zweck |
|---|---|---|---|
| `dense` | `bool` | `false` | Bootstrap `table-sm`. |
| `striped` | `bool` | `false` | `table-striped`. |
| `hover` | `bool` | `false` | `table-hover`. |

**Slot**: `default` — sollte `<thead>` + `<tbody>` enthalten.

```blade
<div class="table-responsive">
    <x-ui.data-table striped hover>
        <thead><tr><th>Username</th>...</tr></thead>
        <tbody>...</tbody>
    </x-ui.data-table>
</div>
```

---

## 3. Formulare

### `<x-forms.form>` — Form-Wrapper

| Prop | Default | Zweck |
|---|---|---|
| `action` | `''` | Form-Action-URL. |
| `method` | `null` | HTTP-Method (`POST`, `PUT` für Spoofing). |
| `novalidate` | `true` | HTML-Validation deaktivieren. |

### `<x-forms.input>` — Text-Input

Pflicht: `name`, `label`. Plus `type`, `value`, `required`, `placeholder`, `min`, `max`, `step`, `colClass`.

### `<x-forms.select>` — Select

Pflicht: `name`, `label`, `options` (`array<key, label>`).

### `<x-forms.textarea>` — Textarea

Pflicht: `name`, `label`. Plus `rows` (Default 3).

### `<x-forms.checkbox>` — Checkbox

Pflicht: `name`, `label`. Plus `checked`, `value`.

### `<x-forms.form-actions>` — Submit/Cancel-Block

| Prop | Default | Zweck |
|---|---|---|
| `submitLabel` | `'Speichern'` | Submit-Button-Text. |
| `cancelUrl` | `null` | Wenn gesetzt → Cancel-Link. |
| `cancelLabel` | `'Abbrechen'` | Cancel-Button-Text. |

---

## 4. Navigation und Tabs

### `<x-navigation>` — Hauptnavigation

Wird über `Shared\NavigationComposer` befüllt. Props: `currentSection`, optional `items`.

### `<x-tabs>` / `<x-sidebar-tabs>` / `<x-filters.filter-tabs>`

Drei spezialisierte Tab-Varianten:
- **`tabs`**: horizontale Inline-Tabs. Nutzt `Shared\TabsComposer`.
- **`sidebar-tabs`**: Sidebar-Variante mit Title/Description. Nutzt `Shared\SidebarTabsComposer`.
- **`filters.filter-tabs`**: Filter-Tab-Leiste über Listen. Nutzt `Shared\FilterTabsComposer`.

Jede Variante akzeptiert: `tabs` (`array<key, label>`), `activeTab`, `baseUrl`, `tabParam`.

### `<x-filters.filter-form>` — Filter-Block

Header für Tabellen-Filter mit Reset-Action. Slot: `default` (Filter-Inputs).

---

## 5. Domain-Komponenten

### `<x-flash-messages>` — Flash-/Alert-Block

Liest aus Session, akzeptiert auch Props.

### `<x-order-status>` — Order-Status-Badge

`order` Prop; wird in `fulfillment.orders.show` benutzt.

---

## 6. Verboten / nicht erlaubt

- **Kein eigenes Bootstrap-Cluster** wie `class="d-flex justify-content-between align-items-center mb-4"` — verwende `<x-ui.page-header>`.
- **Keine duplizierten Tabellen** — verwende `<x-ui.data-table>`.
- **Keine eigenen Action-Buttons** im Section-Header — verwende `<x-ui.action-link>`.
- **Kein `class="form-label small text-uppercase text-muted"`** mehr direkt — wird in `<x-forms.input>`/`<x-forms.select>` automatisch via Label-Style gerendert.

---

## 7. Komponenten-Erweiterung

Neue Komponenten kommen unter:
- `resources/views/components/ui/<name>.blade.php` für **fachlich neutrale** Bausteine
- `resources/views/components/forms/<name>.blade.php` für **Formularteile**
- `resources/views/components/filters/<name>.blade.php` für **Filter-/Listen-Steuerelemente**
- `resources/views/components/<name>.blade.php` für **fachliche Komponenten**

Anschließend in dieser Datei dokumentieren.
