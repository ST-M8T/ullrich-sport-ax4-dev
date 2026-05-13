@props([
    'status' => 'active',
])

{{--
    Wiederverwendbarer Status-Badge fuer Katalog-Eintraege.
    Engineering-Handbuch §51: Statusinformation nicht NUR per Farbe — Icon + Text.
    §75.1: Eine Stelle fuer das wiederkehrende Badge-Pattern.

    Erlaubte Werte:  active | deprecated | unknown
--}}
@php
    $variant = match ($status) {
        'active'     => ['class' => 'bg-success-subtle text-success-emphasis border border-success-subtle',     'icon' => 'fa-check-circle', 'label' => 'aktiv'],
        'deprecated' => ['class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',     'icon' => 'fa-archive',      'label' => 'deprecated'],
        default      => ['class' => 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle', 'icon' => 'fa-question-circle','label' => 'unbekannt'],
    };
@endphp

<span class="badge {{ $variant['class'] }} d-inline-flex align-items-center gap-1" role="status">
    <i class="fa {{ $variant['icon'] }} icon" aria-hidden="true"></i>
    <span>{{ $variant['label'] }}</span>
</span>
