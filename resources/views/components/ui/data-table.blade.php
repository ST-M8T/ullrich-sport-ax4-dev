@props([
    'dense' => false,
    'striped' => false,
    'hover' => false,
])

{{-- Wiederverwendbare Daten-Tabelle.
     Verkapselt die wiederkehrenden `table align-middle mb-0` Cluster und wrappt
     intern in `.table-responsive`, damit Aufrufer keinen eigenen Wrapper bauen müssen.

     - dense: `table-sm` (kompakte Reihen)
     - striped: `table-striped` (alternierende Hintergrundfarbe)
     - hover: `table-hover` (Hover-Highlight)

     Slots:
     - default: thead + tbody.
     - caption: optional, wird als <caption> für Screenreader-A11y gerendert. --}}
<div class="table-responsive">
    <table {{ $attributes->class([
        'table align-middle mb-0',
        'table-sm' => $dense,
        'table-striped' => $striped,
        'table-hover' => $hover,
    ])->merge() }}>
        @isset($caption)
            <caption>{{ $caption }}</caption>
        @endisset
        {{ $slot }}
    </table>
</div>
