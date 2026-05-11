@extends('layouts.admin', [
    'pageTitle' => 'Versandprofile',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Versand'],
    ],
])

@section('content')
    <x-ui.page-header title="Versandprofile" subtitle="Pflege der Plenty-Versandprofile für Export und Zuordnungen.">
        <x-slot:actions>
            <a href="{{ route('fulfillment.masterdata.freight.create') }}" class="btn btn-primary">
            Neues Versandprofil
        </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card">
        <div class="card-body p-0">
            <div class="p-3 border-bottom bg-light-subtle">
                <x-filters.filter-form :action="route('fulfillment.masterdata.freight.index')" :filters="$filters ?? []">
                    <x-forms.input
                        name="search"
                        label="Suche"
                        type="search"
                        :value="$filters['search'] ?? ''"
                        placeholder="ID oder Bezeichnung…"
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
                        <th scope="col">ID</th>
                        <th scope="col">Bezeichnung</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($freightProfiles as $profile)
                        <tr>
                            <td>{{ $profile->shippingProfileId()->toInt() }}</td>
                            <td>{{ $profile->label() ?? '—' }}</td>
                            <td class="text-end">
                                <a
                                    href="{{ route('fulfillment.masterdata.freight.edit', $profile->shippingProfileId()->toInt()) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Bearbeiten
                                </a>
                                <form
                                    action="{{ route('fulfillment.masterdata.freight.destroy', $profile->shippingProfileId()->toInt()) }}"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Versandprofil wirklich löschen?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Noch keine Versandprofile gepflegt.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>
    </div>

    <x-ui.pagination-footer :paginator="$paginationLinks" label="Einträgen" />
@endsection
