# CSS Architektur - Modulares Design System

## Struktur

```
resources/css/
├── app.css                    # Entry Point - importiert alle Module
├── variables.css              # CSS Variables (Farben, Spacing, etc.)
├── reset.css                  # CSS Reset (border-radius entfernen)
├── base.css                   # Base Styles (HTML-Element-Styles)
├── layout.css                 # Layout Styles
├── utilities/                 # Utility-Klassen
│   ├── layout.css            # Layout-Utilities (stack, grid-auto, etc.)
│   ├── spacing.css           # Spacing-Utilities (px-1, etc.)
│   ├── typography.css        # Typography-Utilities (Platzhalter)
│   └── hint-box.css          # Hint-Box Utility
└── components/                # Komponenten (CSS-Module)
    ├── alert.module.css
    ├── badge.module.css
    ├── button.module.css
    ├── card.module.css
    ├── form.module.css
    ├── table.module.css
    ├── modal.module.css
    └── ... (alle 19 Komponenten als Module)
```

## Konventionen

### Datei-Namenskonventionen
- **Komponenten**: Alle Dateien in `components/` müssen `.module.css` Endung haben
- **Utilities**: Alle Dateien in `utilities/` müssen `.css` Endung haben (KEINE `.module.css`)
- **Root-Dateien**: Alle Dateien im Root von `resources/css/` müssen `.css` Endung haben

### Import-Reihenfolge in app.css
1. Tailwind CSS
2. Variables
3. Reset
4. Base
5. Layout
6. Utilities (alphabetisch)
7. Components (alphabetisch)

### Prinzipien
- **SOLID**: Single Responsibility Principle - jede Datei hat eine einzige Verantwortung
- **DRY**: Don't Repeat Yourself - alle Werte in Variablen
- **KISS**: Keep It Simple, Stupid - einfache, klare Struktur
- **YAGNI**: You Aren't Gonna Need It - nur das Nötige
- **SRP**: Single Responsibility Principle - klare Trennung
- **DDD**: Domain-Driven Design - klare Trennung zwischen Utilities und Komponenten

## CSS-Module

Alle Komponenten sind CSS-Module (`.module.css`), die von Vite verarbeitet werden:
- Scoped Names: `[name]__[local]___[hash:base64:5]`
- Code-Splitting aktiviert
- Separate Ausgabepfade für Module

## CSS-Variablen

Alle Farbwerte, Spacing und andere wiederkehrende Werte sind in `variables.css` definiert:
- `--color-white`, `--color-black`
- `--brand-primary`, `--brand-primary-hover`
- `--color-alert-*` (success, error, warning, info)
- `--color-badge-*` (success, warning, info)

## Aufräumung

- Keine verwaisten Dateien
- Keine alten Klassen ohne `.module.css` in components/
- Keine Utilities mit `.module.css`
- Alle Imports sind korrekt und verifiziert
