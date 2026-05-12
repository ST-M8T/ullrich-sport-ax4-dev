{{--
    DHL Package Editor (Repeater) — Booking-UI Sub-Komponente.

    Engineering-Handbuch:
      - §52 Formularregel: Labels, Inline-Validation, Schutz vor Mehrfachabsendung,
        klare Fehlertexte. Backend-Validation bleibt Pflicht (DhlBookingRequest).
      - §53 Loading/Empty-State: Wenn 0 Rows -> Hinweis + grayed-out Submit.
      - §75 strikt DRY: Reusable Partial. Nur EINE Quelle der Wahrheit fuer
        Package-Eingabezeilen.

    Erwartete Variablen:
      - $order              ShipmentOrder
      - $packages           array<ShipmentPackage>
      - $defaultPackageType string (z.B. "PAL")
      - $bookingActionUrl   string (POST-Ziel)
      - $productCode        string (optional Vorbelegung)
      - $payerCode          string (optional Vorbelegung, default DAP)

    Erzeugte Form-Feld-Namen (siehe DhlBookingRequest::rules()):
      pieces[i][number_of_pieces]
      pieces[i][package_type]
      pieces[i][weight]
      pieces[i][length]
      pieces[i][width]
      pieces[i][height]
      pieces[i][marks_and_numbers]
--}}
@php
    /** @var \App\Domain\Fulfillment\Orders\ShipmentOrder $order */
    /** @var array<int,\App\Domain\Fulfillment\Orders\ShipmentPackage> $packages */
    $editorDefaultPackageType = strtoupper(trim((string) ($defaultPackageType ?? 'PAL'))) ?: 'PAL';
    // Bewusst KEIN Default fuer payer_code: User muss Frachtzahler aktiv waehlen.
    // (DhlBookingRequest erzwingt 'required' — Defense in Depth, §15/§19.)
    $editorPayerCode = strtoupper(trim((string) old('payer_code', $payerCode ?? '')));
    $editorProductCode = strtoupper(trim((string) ($productCode ?? '')));

    $payerCodeOptions = [
        'DAP' => 'DAP — Empfaenger zahlt nicht; wir zahlen Fracht (haeufigster Fall).',
        'DDP' => 'DDP — wir zahlen Fracht und Zoll.',
        'EXW' => 'EXW — Empfaenger holt ab und zahlt Fracht.',
        'CIP' => 'CIP — wir zahlen Fracht und Versicherung.',
    ];

    $editorSenderProfiles = collect($senderProfiles ?? []);
    $editorSenderProfileOptions = $editorSenderProfiles
        ->mapWithKeys(fn ($profile) => [
            (int) $profile->id()->toInt() => $profile->displayName().' ('.$profile->senderCode().')',
        ])->all();
    $editorSenderProfileDefault = old('sender_profile_id', $order->senderProfileId()?->toInt() ?? array_key_first($editorSenderProfileOptions));

    $editorFreightProfiles = collect($freightProfiles ?? []);
    $editorFreightProfileOptions = $editorFreightProfiles
        ->mapWithKeys(fn ($profile) => [
            (int) $profile->shippingProfileId()->toInt() => ($profile->label() ?? ('Profil #'.$profile->shippingProfileId()->toInt())),
        ])->all();
    $editorFreightProfileDefault = old('freight_profile_id', $order->freightProfileId());

    $editorPickupDateMin = now()->format('Y-m-d');
    $editorPickupDateValue = old('pickup_date', '');

    $editorOldServices = (array) old('additional_services', []);
    $editorOldServicesAssoc = array_fill_keys(array_map('strval', $editorOldServices), true);
    $editorPickupDateMinDisplay = \Carbon\Carbon::parse($editorPickupDateMin)->format('d.m.Y');

    $editorRows = [];
    foreach ($packages as $package) {
        $lengthMm = $package->lengthMillimetres();
        $widthMm = $package->widthMillimetres();
        $heightMm = $package->heightMillimetres();

        $editorRows[] = [
            'number_of_pieces' => max(1, (int) $package->quantity()),
            'package_type' => $editorDefaultPackageType,
            'weight' => $package->weightKg() !== null
                ? number_format((float) $package->weightKg(), 2, '.', '')
                : '',
            'length' => $lengthMm !== null ? (string) (int) round($lengthMm / 10) : '',
            'width' => $widthMm !== null ? (string) (int) round($widthMm / 10) : '',
            'height' => $heightMm !== null ? (string) (int) round($heightMm / 10) : '',
            'marks_and_numbers' => $package->packageReference() ?? '',
        ];
    }
@endphp

<x-ui.info-card>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Pakete für DHL-Buchung</h2>
        <button
            type="button"
            class="btn btn-outline-primary btn-sm"
            data-package-editor-add
            aria-label="Weiteres Paket hinzufügen"
        >+ Paket hinzufügen</button>
    </div>

    {{-- Prominenter Error-Banner fuer DHL-API-Fehler (4xx/5xx vom Backend). §53. --}}
    @error('dhl_booking')
        <div
            class="alert alert-danger d-flex align-items-start gap-2"
            role="alert"
            data-dhl-booking-error
        >
            <strong class="me-1">DHL-Buchung fehlgeschlagen:</strong>
            <span>{{ $message }}</span>
        </div>
    @enderror

    {{-- Empty-State: Auftrag hat 0 Pakete -> Hinweis vor der Buchung. §53. --}}
    @if (count($packages) === 0)
        <div class="alert alert-info" role="status" data-package-editor-no-packages>
            Bitte konfigurieren Sie mindestens ein Paket vor der Buchung.
        </div>
    @endif

    <form
        method="POST"
        action="{{ $bookingActionUrl }}"
        data-package-editor-form
        data-default-package-type="{{ $editorDefaultPackageType }}"
        data-dhl-services-url="{{ url('/api/admin/dhl/services') }}"
        novalidate
    >
        @csrf
        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label for="dhl-product-select" class="form-label">
                    DHL-Produkt <span class="text-danger">*</span>
                </label>
                <div
                    data-dhl-product-selector
                    data-products-url="{{ url('/api/admin/dhl/products') }}"
                    data-default-product-code="{{ old('product_code', $editorProductCode) }}"
                >
                    <select
                        id="dhl-product-select"
                        name="product_code"
                        class="form-select"
                        required
                        aria-busy="true"
                        aria-describedby="dhl-pkg-product-code-help"
                        data-dhl-product-select
                        data-dhl-product-code-input
                        disabled
                    >
                        <option value="">Lade Produkte …</option>
                    </select>
                    <small
                        id="dhl-pkg-product-code-help"
                        class="form-text text-muted"
                        role="status"
                        aria-live="polite"
                        data-dhl-product-status
                    >Produkte werden geladen …</small>
                </div>
            </div>
            <div class="col-md-4">
                <label for="dhl-pkg-default-type" class="form-label">Standard-Pakettyp <span class="text-danger">*</span></label>
                <input
                    type="text"
                    id="dhl-pkg-default-type"
                    name="default_package_type"
                    class="form-control text-uppercase"
                    maxlength="4"
                    minlength="1"
                    pattern="[A-Z0-9]{1,4}"
                    value="{{ old('default_package_type', $editorDefaultPackageType) }}"
                    required
                >
            </div>
            <div class="col-md-4">
                <label for="dhl-pkg-pickup-date" class="form-label">Abholdatum (optional)</label>
                <input
                    type="date"
                    id="dhl-pkg-pickup-date"
                    name="pickup_date"
                    class="form-control"
                    min="{{ $editorPickupDateMin }}"
                    value="{{ $editorPickupDateValue }}"
                    aria-describedby="dhl-pkg-pickup-date-help"
                >
                <small id="dhl-pkg-pickup-date-help" class="text-muted">
                    Frueheste Abholung: {{ $editorPickupDateMinDisplay }}.
                </small>
            </div>
        </div>

        {{-- Frachtzahler (Payer-Code) — Pflichtfeld ohne Default. §51 Fieldset/Legend. --}}
        <fieldset class="mb-3" data-dhl-payer-code>
            <legend class="form-label fs-6 mb-2">
                Frachtzahler <span class="text-danger">*</span>
            </legend>
            <div class="d-flex flex-wrap gap-3" role="radiogroup" aria-required="true">
                @foreach ($payerCodeOptions as $code => $description)
                    @php $inputId = 'dhl-pkg-payer-'.strtolower($code); @endphp
                    <div class="form-check">
                        <input
                            type="radio"
                            class="form-check-input"
                            id="{{ $inputId }}"
                            name="payer_code"
                            value="{{ $code }}"
                            @checked($editorPayerCode === $code)
                            required
                            aria-describedby="{{ $inputId }}-help"
                        >
                        <label class="form-check-label" for="{{ $inputId }}">
                            <strong>{{ $code }}</strong>
                        </label>
                        <small id="{{ $inputId }}-help" class="d-block text-muted small">{{ $description }}</small>
                    </div>
                @endforeach
            </div>
            <small class="text-muted">DAP ist der haeufigste Fall — bitte trotzdem aktiv bestaetigen.</small>
        </fieldset>

        {{-- Senderprofil (Pflicht) und Frachtprofil-Override (optional). --}}
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label for="dhl-pkg-sender-profile" class="form-label">
                    Senderprofil <span class="text-danger">*</span>
                </label>
                <select
                    id="dhl-pkg-sender-profile"
                    name="sender_profile_id"
                    class="form-select"
                    required
                >
                    @if (count($editorSenderProfileOptions) === 0)
                        <option value="">— Kein Senderprofil verfuegbar —</option>
                    @else
                        @foreach ($editorSenderProfileOptions as $profileId => $label)
                            <option value="{{ $profileId }}" @selected((int) $editorSenderProfileDefault === (int) $profileId)>
                                {{ $label }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-md-6">
                <label for="dhl-pkg-freight-profile" class="form-label">Frachtprofil (optional Override)</label>
                <select
                    id="dhl-pkg-freight-profile"
                    name="freight_profile_id"
                    class="form-select"
                    aria-describedby="dhl-pkg-freight-profile-help"
                >
                    <option value="">— Standard aus Auftrag —</option>
                    @foreach ($editorFreightProfileOptions as $profileId => $label)
                        <option value="{{ $profileId }}" @selected((int) $editorFreightProfileDefault === (int) $profileId)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <small id="dhl-pkg-freight-profile-help" class="text-muted">
                    Leer lassen, um das im Auftrag hinterlegte Profil zu verwenden.
                </small>
            </div>
        </div>

        {{-- Zusatzleistungen — dynamisch zu Produktcode geladen (§53 States). --}}
        <div
            class="mb-3"
            data-dhl-services-container
            data-default-services="{{ json_encode(array_values($editorOldServices)) }}"
        >
            <label class="form-label fs-6 mb-2 d-block">Zusatzleistungen (optional)</label>
            <div
                class="form-text small text-muted"
                role="status"
                aria-live="polite"
                data-dhl-services-status
            >
                Bitte zuerst einen Produkt-Code eingeben, um Zusatzleistungen zu laden.
            </div>
            <div
                class="d-flex flex-wrap gap-2 mt-2"
                role="group"
                aria-label="Verfuegbare Zusatzleistungen"
                data-dhl-services-list
            ></div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle" data-package-editor-table>
                <thead>
                    <tr>
                        <th scope="col" class="dhl-pkg-col-count">Anzahl</th>
                        <th scope="col" class="dhl-pkg-col-type">Typ</th>
                        <th scope="col" class="dhl-pkg-col-weight">Gewicht (kg)</th>
                        <th scope="col" class="dhl-pkg-col-dim">Länge (cm)</th>
                        <th scope="col" class="dhl-pkg-col-dim">Breite (cm)</th>
                        <th scope="col" class="dhl-pkg-col-dim">Höhe (cm)</th>
                        <th scope="col">Marks/Numbers</th>
                        <th scope="col" class="dhl-pkg-col-action text-end">Aktion</th>
                    </tr>
                </thead>
                <tbody data-package-editor-rows>
                    @forelse ($editorRows as $rowIndex => $row)
                        @include('fulfillment.orders._dhl-package-editor-row', [
                            'rowIndex' => $rowIndex,
                            'row' => $row,
                            'defaultPackageType' => $editorDefaultPackageType,
                        ])
                    @empty
                        @include('fulfillment.orders._dhl-package-editor-row', [
                            'rowIndex' => 0,
                            'row' => [
                                'number_of_pieces' => 1,
                                'package_type' => $editorDefaultPackageType,
                                'weight' => '',
                                'length' => '',
                                'width' => '',
                                'height' => '',
                                'marks_and_numbers' => '',
                            ],
                            'defaultPackageType' => $editorDefaultPackageType,
                        ])
                    @endforelse
                </tbody>
            </table>
        </div>

        <div
            class="alert alert-warning d-none"
            role="alert"
            data-package-editor-empty
        >
            Mindestens 1 Paket erforderlich.
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button
                type="submit"
                class="btn btn-primary"
                data-package-editor-submit
                data-label-default="DHL-Buchung absenden"
                data-label-loading="Sende ..."
            >
                <span
                    class="spinner-border spinner-border-sm d-none me-2"
                    role="status"
                    aria-hidden="true"
                    data-package-editor-spinner
                ></span>
                <span data-package-editor-submit-label>DHL-Buchung absenden</span>
            </button>
        </div>
    </form>
</x-ui.info-card>
