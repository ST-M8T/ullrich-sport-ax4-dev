@extends('layouts.admin', [
    'pageTitle' => 'Variantenprofile',
    'currentSection' => 'fulfillment-masterdata',
])


@section('content')
    <x-ui.page-header title="Variantenprofile" subtitle="Konfiguration der Standard-Verpackung und Montage je Plenty-Item.">
        <x-slot:actions>
            <a href="{{ route('fulfillment.masterdata.variations.create') }}" class="btn btn-primary">
            Neues Profil
        </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card">
        <div class="card-body p-0">
            <div class="p-3 border-bottom bg-light-subtle">
                <x-filters.filter-form :action="route('fulfillment.masterdata.variations.index')" :filters="$filters ?? []">
                    <x-forms.input
                        name="item_id"
                        label="Item-ID"
                        type="number"
                        :value="$filters['item_id'] ?? ''"
                        min="1"
                        col-class="col-md-3"
                    />
                    <x-forms.input
                        name="variation_id"
                        label="Variation-ID"
                        type="number"
                        :value="$filters['variation_id'] ?? ''"
                        col-class="col-md-3"
                    />
                    <x-forms.select
                        name="default_state"
                        label="Standardzustand"
                        :options="['' => 'Alle', 'assembled' => 'Vormontiert', 'kit' => 'Bausatz']"
                        :value="$filters['default_state'] ?? ''"
                        col-class="col-md-3"
                    />
                    <x-forms.input
                        name="search"
                        label="Suche"
                        type="search"
                        :value="$filters['search'] ?? ''"
                        placeholder="Variationsname…"
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
                        <th scope="col">Item</th>
                        <th scope="col">Variation</th>
                        <th scope="col">Name</th>
                        <th scope="col">Zustand</th>
                        <th scope="col">Verpackung</th>
                        <th scope="col">Gewicht (kg)</th>
                        <th scope="col">Vormontage</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($variationProfiles as $profile)
                        @php
                            $packaging = $packagingById->get($profile->defaultPackagingId()->toInt());
                            $assembly = $profile->assemblyOptionId() ? $assemblyById->get($profile->assemblyOptionId()->toInt()) : null;
                        @endphp
                        <tr>
                            <td>#{{ $profile->id()->toInt() }}</td>
                            <td>{{ $profile->itemId() }}</td>
                            <td>{{ $profile->variationId() ?? '—' }}</td>
                            <td>{{ $profile->variationName() ?? '—' }}</td>
                            <td>
                                @if($profile->isDefaultAssembled())
                                    <span class="badge bg-success">Vormontiert</span>
                                @else
                                    <span class="badge bg-secondary">Bausatz</span>
                                @endif
                            </td>
                            <td>{{ $packaging?->packageName() ?? '—' }}</td>
                            <td>{{ $profile->defaultWeightKg() ?? '—' }}</td>
                            <td>{{ $assembly?->assemblyItemId() ?? '—' }}</td>
                            <td class="text-end">
                                <a
                                    href="{{ route('fulfillment.masterdata.variations.edit', $profile->id()->toInt()) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Bearbeiten
                                </a>
                                <form
                                    action="{{ route('fulfillment.masterdata.variations.destroy', $profile->id()->toInt()) }}"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Variantenprofil wirklich löschen?');"
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
                                Noch keine Variantenprofile hinterlegt.
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
