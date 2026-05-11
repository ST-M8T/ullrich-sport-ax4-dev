@extends('layouts.admin', [
    'pageTitle' => 'Verpackungsprofile',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Verpackung'],
    ],
])

@section('content')
    <x-ui.page-header title="Verpackungsprofile" subtitle="Definition der Paletten- und Transportprofile für das Fulfillment.">
        <x-slot:actions>
            <a href="{{ route('fulfillment.masterdata.packaging.create') }}" class="btn btn-primary">
            Neues Profil
        </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card">
        <div class="card-body p-0">
            <div class="p-3 border-bottom bg-light-subtle">
                <x-filters.filter-form :action="route('fulfillment.masterdata.packaging.index')" :filters="$filters ?? []">
                    <x-forms.input
                        name="search"
                        label="Suche"
                        type="search"
                        :value="$filters['search'] ?? ''"
                        placeholder="Name oder Code…"
                        col-class="col-md-6"
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
                        <th scope="col">Bezeichnung</th>
                        <th scope="col">Code</th>
                        <th scope="col">Maße (mm)</th>
                        <th scope="col">Slots</th>
                        <th scope="col">Max / Empf.</th>
                        <th scope="col">Max / Mix</th>
                        <th scope="col">Stapel (Empfänger/Mix)</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($packagingProfiles as $profile)
                        <tr>
                            <td>#{{ $profile->id()->toInt() }}</td>
                            <td>{{ $profile->packageName() }}</td>
                            <td>{{ $profile->packagingCode() ?? '—' }}</td>
                            <td>
                                {{ $profile->lengthMillimetres() }} ×
                                {{ $profile->widthMillimetres() }} ×
                                {{ $profile->heightMillimetres() }}
                            </td>
                            <td>{{ $profile->truckSlotUnits() }}</td>
                            <td>{{ $profile->maxUnitsPerPalletSameRecipient() }}</td>
                            <td>{{ $profile->maxUnitsPerPalletMixedRecipient() }}</td>
                            <td>
                                {{ $profile->maxStackablePalletsSameRecipient() }} /
                                {{ $profile->maxStackablePalletsMixedRecipient() }}
                            </td>
                            <td class="text-end">
                                <a
                                    href="{{ route('fulfillment.masterdata.packaging.edit', $profile->id()->toInt()) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Bearbeiten
                                </a>
                                <form
                                    action="{{ route('fulfillment.masterdata.packaging.destroy', $profile->id()->toInt()) }}"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Verpackungsprofil wirklich löschen?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Noch keine Verpackungsprofile vorhanden.
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
