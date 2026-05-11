@extends('layouts.admin', [
    'pageTitle' => 'Vormontage-Optionen',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Montage'],
    ],
])


@section('content')
    <x-ui.page-header title="Vormontage-Optionen" subtitle="Zuordnung von Montage-Artikeln zu Verpackungsprofilen und Gewichten.">
        <x-slot:actions>
            <a href="{{ route('fulfillment.masterdata.assembly.create') }}" class="btn btn-primary">
            Neue Vormontage
        </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card">
        <div class="card-body p-0">
            <div class="p-3 border-bottom bg-light-subtle">
                <x-filters.filter-form :action="route('fulfillment.masterdata.assembly.index')" :filters="$filters ?? []">
                    <x-forms.input
                        name="assembly_item_id"
                        label="Artikel-ID"
                        type="number"
                        :value="$filters['assembly_item_id'] ?? ''"
                        min="1"
                        col-class="col-md-3"
                    />
                    <x-forms.select
                        name="assembly_packaging_id"
                        label="Verpackung"
                        :options="collect($packagingById)->mapWithKeys(fn($profile) => [$profile->id()->toInt() => $profile->packageName()])->prepend('Alle Verpackungen', '')->all()"
                        :value="(string)($filters['assembly_packaging_id'] ?? '')"
                        col-class="col-md-3"
                    />
                    <x-forms.input
                        name="search"
                        label="Beschreibung"
                        type="search"
                        :value="$filters['search'] ?? ''"
                        placeholder="Freitextsuche…"
                        col-class="col-md-3"
                    />
                    <x-forms.select
                        name="per_page"
                        label="Pro Seite"
                        :options="array_combine([10, 25, 50, 100, 200], [10, 25, 50, 100, 200])"
                        :value="(string)($filters['per_page'] ?? 25)"
                        col-class="col-md-3"
                    />
                </x-filters.filter-form>
            </div>

            <div class="table-responsive">
                <x-ui.data-table striped hover>
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Artikel-ID</th>
                        <th scope="col">Verpackung</th>
                        <th scope="col">Gewicht (kg)</th>
                        <th scope="col">Beschreibung</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($assemblyOptions as $option)
                        @php
                            $packaging = $packagingById->get($option->assemblyPackagingId()->toInt());
                        @endphp
                        <tr>
                            <td>#{{ $option->id()->toInt() }}</td>
                            <td>{{ $option->assemblyItemId() }}</td>
                            <td>{{ $packaging?->packageName() ?? '—' }}</td>
                            <td>{{ $option->assemblyWeightKg() ?? '—' }}</td>
                            <td>{{ $option->description() ?? '—' }}</td>
                            <td class="text-end">
                                <a
                                    href="{{ route('fulfillment.masterdata.assembly.edit', $option->id()->toInt()) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Bearbeiten
                                </a>
                                <form
                                    action="{{ route('fulfillment.masterdata.assembly.destroy', $option->id()->toInt()) }}"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Vormontage wirklich löschen?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Noch keine Vormontagen gepflegt.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>
    </div>

    <x-ui.pagination-footer :paginator="$paginationLinks" label="Einträgen" />
@endsection
