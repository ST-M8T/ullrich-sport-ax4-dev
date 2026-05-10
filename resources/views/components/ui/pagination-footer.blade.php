@props([
    'paginator',
    'label' => 'Einträgen',
])

{{-- Wiederverwendbarer Pagination-Footer für Listen.
     Kapselt den Cluster `d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3`
     mit Item-Count-Text und Bootstrap-Pagination-Links.

     Erwartet `\Illuminate\Pagination\LengthAwarePaginator`, `PaginatorLinkGenerator`,
     oder `PaginatedResult` (via `toLinks()`).
     Optional `label` für die Pluralform der Entitäten (Default: "Einträgen"). --}}
@php
    // Unterstütze LengthAwarePaginator, PaginatorLinkGenerator, oder PaginatedResult (via toLinks)
    if (method_exists($paginator, 'toLinks')) {
        $paginator = $paginator->toLinks(request()->route()?->getName() ?? '', request()->query());
    }

    // PaginatedResult hat firstItem/lastItem/total nicht — LengthAwarePaginator schon
    if (method_exists($paginator, 'firstItem')) {
        $from = $paginator->firstItem() ?? 0;
        $to = $paginator->lastItem() ?? 0;
        $total = $paginator->total();
    } else {
        // PaginatedResult: currentPage, perPage, total
        $perPage = $paginator->perPage();
        $currentPage = $paginator->currentPage();
        $total = $paginator->total();
        $from = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = min($currentPage * $perPage, $total);
    }

    // Filter und Query-Parameter über die Pagination-Links erhalten.
    $links = method_exists($paginator, 'withQueryString')
        ? $paginator->withQueryString()->onEachSide(1)
        : (method_exists($paginator, 'onEachSide') ? $paginator->onEachSide(1) : $paginator);
@endphp

<div {{ $attributes->class('d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3')->merge() }}>
    <div class="text-muted small">
        Zeige {{ $from }}–{{ $to }} von {{ $total }} {{ $label }}
    </div>
    {{ $links->links() }}
</div>
