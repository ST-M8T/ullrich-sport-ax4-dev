@props([
    'syncStatus',
    'canSync' => false,
    'triggerUrl' => null,
])

{{--
    Sync-Status-Banner fuer den DHL-Katalog.
    Engineering-Handbuch §51: Farbe + Icon + Text.
    §75: Eine zentrale Stelle fuer dieses wiederkehrende Banner.

    Variants (auto):
      - rot  : last attempt liegt vor (oder ohne) last success → Fehler
      - gelb : noch nie gelaufen ODER laufender Trigger erkennbar
      - gruen: last_success_at >= last_attempt_at
--}}
@php
    /** @var \App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlCatalogSyncStatus $syncStatus */
    $lastAttempt = $syncStatus->lastAttemptAt;
    $lastSuccess = $syncStatus->lastSuccessAt;
    $lastError   = $syncStatus->lastError;
    $failures    = $syncStatus->consecutiveFailures;

    if ($lastAttempt === null && $lastSuccess === null) {
        $level = 'warning';
        $title = 'Noch kein Sync ausgefuehrt';
        $description = 'Bitte ersten DHL-Katalog-Sync starten, damit Produkte sichtbar sind.';
    } elseif ($lastAttempt !== null && ($lastSuccess === null || $lastSuccess < $lastAttempt)) {
        $level = 'danger';
        $title = sprintf('Letzter Sync fehlgeschlagen (%d aufeinanderfolgende Fehler)', $failures);
        $description = $lastError !== null
            ? mb_substr($lastError, 0, 200) . (mb_strlen($lastError) > 200 ? ' …' : '')
            : 'Keine Fehlermeldung erfasst.';
    } else {
        $level = 'success';
        $title = 'Letzter Sync erfolgreich';
        $description = $lastSuccess !== null
            ? 'Erfolgreich am ' . $lastSuccess->format('d.m.Y H:i') . ' Uhr.'
            : '';
    }

    $variant = match ($level) {
        'success' => ['class' => 'alert-success', 'icon' => 'fa-check-circle'],
        'danger'  => ['class' => 'alert-danger',  'icon' => 'fa-exclamation-triangle'],
        default   => ['class' => 'alert-warning', 'icon' => 'fa-info-circle'],
    };
@endphp

<div
    class="alert {{ $variant['class'] }} d-flex align-items-start gap-3"
    role="status"
    aria-live="polite"
    data-dhl-catalog-sync-banner
    data-level="{{ $level }}"
>
    <i class="fa {{ $variant['icon'] }} icon mt-1" aria-hidden="true"></i>
    <div class="flex-grow-1">
        <strong data-dhl-catalog-sync-title>{{ $title }}</strong>
        @if($description !== '')
            <div class="small mt-1" data-dhl-catalog-sync-description>{{ $description }}</div>
        @endif
    </div>

    @if($canSync && $triggerUrl !== null)
        <form method="post" action="{{ $triggerUrl }}" class="m-0">
            @csrf
            <button
                type="submit"
                class="btn btn-sm btn-outline-primary"
                data-dhl-catalog-sync-trigger
                onclick="return confirm('Manuellen DHL-Katalog-Sync jetzt starten?');"
            >
                <i class="fa fa-refresh icon" aria-hidden="true"></i>
                Sync jetzt starten
            </button>
        </form>
    @endif
</div>
