@props([
    'name' => 'additional_services',
    'productCode' => null,
    'fromCountry' => null,
    'toCountry' => null,
    'payerCode' => null,
    'selectedServices' => [],
    'mode' => 'booking',
    'readOnly' => false,
    'endpointUrl' => null,
    'intersectionUrl' => null,
    'routings' => null,
    'containerId' => null,
])

{{--
    Wiederverwendbare DHL-Akkordeon-Komponente fuer Zusatzleistungen.

    Engineering-Handbuch:
      - §75 DRY: identische Component in PROJ-4 (Profil) und PROJ-5 (Buchung).
      - §35-§39 Frontend-Schichten: Blade rendert nur das Skelett +
        Konfigurations-Attribute, der JS-Controller `dhl-allowed-services-accordion.js`
        uebernimmt API-Aufruf, Render und Re-Fetch bei Routing-Wechsel.
      - §51 A11y: aria-live fuer Statuszonen, aria-busy waehrend Laden,
        Akkordeon-Sektionen mit aria-expanded/aria-controls (vom Controller gesetzt).
      - §45 Keine direkten fetch-Calls in Blade — JS-Service-Layer uebernimmt.

    Props:
      - name             Form-Input-Prefix (z.B. 'additional_services' oder
                         'dhl_default_service_parameters').
      - productCode      Vorbelegtes Produkt fuer initialen Load (oder null →
                         Banner "Produkt waehlen").
      - fromCountry      ISO-3166-1 alpha-2.
      - toCountry        ISO-3166-1 alpha-2.
      - payerCode        SENDER | RECIPIENT | THIRD_PARTY.
      - selectedServices Vorbelegung: [['code' => 'COD', 'parameters' => [...]], ...]
      - mode             'profile' | 'booking'  (steuert nur Hilfetexte).
      - readOnly         true → Checkboxes disabled.
      - endpointUrl      Override fuer Show-Endpoint.
      - intersectionUrl  Override fuer Intersection-Endpoint (Bulk).
      - routings         Array von Routings fuer Bulk-Mode (statt einzelner Produkt-Kombi).
      - containerId      Optionaler DOM-Id (sonst auto-generiert).
--}}

@php
    $resolvedEndpoint = $endpointUrl
        ?? (\Illuminate\Support\Facades\Route::has('api.dhl.catalog.allowed-services')
            ? route('api.dhl.catalog.allowed-services')
            : '/api/admin/dhl/catalog/allowed-services');

    $resolvedIntersectionEndpoint = $intersectionUrl
        ?? (\Illuminate\Support\Facades\Route::has('api.dhl.catalog.allowed-services.intersection')
            ? route('api.dhl.catalog.allowed-services.intersection')
            : '/api/admin/dhl/catalog/allowed-services/intersection');

    $domId = $containerId ?: 'dhl-services-accordion-' . \Illuminate\Support\Str::random(8);

    // Normalize preselected to a map  code => parameters[]
    $preselectedMap = [];
    foreach ((array) $selectedServices as $entry) {
        if (! is_array($entry) || empty($entry['code'])) {
            continue;
        }
        $code = strtoupper((string) $entry['code']);
        $preselectedMap[$code] = is_array($entry['parameters'] ?? null) ? $entry['parameters'] : [];
    }
@endphp

<div
    id="{{ $domId }}"
    class="dhl-services-accordion"
    data-dhl-services-accordion
    data-mode="{{ $mode }}"
    data-input-name="{{ $name }}"
    data-endpoint-url="{{ $resolvedEndpoint }}"
    data-intersection-url="{{ $resolvedIntersectionEndpoint }}"
    @if(! is_null($productCode)) data-product-code="{{ $productCode }}" @endif
    @if(! is_null($fromCountry)) data-from-country="{{ $fromCountry }}" @endif
    @if(! is_null($toCountry)) data-to-country="{{ $toCountry }}" @endif
    @if(! is_null($payerCode)) data-payer-code="{{ $payerCode }}" @endif
    @if(! is_null($routings)) data-routings='@json($routings)' @endif
    @if($readOnly) data-read-only="true" @endif
    data-preselected='@json($preselectedMap)'
    aria-busy="false"
    aria-live="polite"
    role="region"
    aria-label="DHL-Zusatzleistungen"
>
    {{-- Idle/empty/error/loading container — controller toggelt Sichtbarkeit --}}
    <div data-dhl-services-state="idle" class="text-muted small">
        @if($mode === 'profile')
            Diese Zusatzleistungen werden bei Buchungen mit diesem Versandprofil automatisch vorbelegt.
            Bitte zuerst ein DHL-Produkt auswaehlen.
        @else
            Bitte zuerst Produkt und Routing waehlen, um verfuegbare Zusatzleistungen zu laden.
        @endif
    </div>

    <div data-dhl-services-state="loading" class="d-none" aria-hidden="true">
        <div class="placeholder-glow">
            <div class="placeholder col-4 mb-2"></div>
            <div class="placeholder col-12 mb-1" style="height: 2rem;"></div>
            <div class="placeholder col-12 mb-1" style="height: 2rem;"></div>
            <div class="placeholder col-12" style="height: 2rem;"></div>
        </div>
    </div>

    <div data-dhl-services-state="empty" class="alert alert-info d-none" role="status">
        Fuer dieses Produkt sind keine Zusatzleistungen verfuegbar.
    </div>

    <div data-dhl-services-state="error" class="alert alert-danger d-none" role="alert">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <span data-dhl-services-error-message>Zusatzleistungen konnten nicht geladen werden.</span>
            <button type="button" class="btn btn-sm btn-outline-danger" data-dhl-services-retry>
                Erneut versuchen
            </button>
        </div>
    </div>

    <div data-dhl-services-deprecated-banner class="alert alert-warning d-none" role="status">
        <span data-dhl-services-deprecated-text>Dieses DHL-Produkt ist abgekuendigt.</span>
    </div>

    <div data-dhl-services-state="success" class="accordion d-none" data-dhl-services-categories>
        {{-- Kategorien werden vom Controller erzeugt --}}
    </div>
</div>
