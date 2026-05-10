@extends('layouts.admin', [
    'pageTitle' => 'Senderprofile',
    'currentSection' => 'fulfillment-masterdata',
])

@section('content')
    <x-ui.page-header title="Senderprofile" subtitle="Absender-Adressen und Kontaktinformationen für Neutral- und Versandprofile.">
        <x-slot:actions>
            <a href="{{ route('fulfillment.masterdata.senders.create') }}" class="btn btn-primary">
            Neues Senderprofil
        </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card">
        <div class="card-body p-0">
            <div class="p-3 border-bottom bg-light-subtle">
                <x-filters.filter-form :action="route('fulfillment.masterdata.senders.index')" :filters="$filters ?? []">
                    <x-forms.input
                        name="search"
                        label="Suche"
                        type="search"
                        :value="$filters['search'] ?? ''"
                        placeholder="Code, Name oder Firma…"
                        col-class="col-md-5"
                    />
                    <x-forms.input
                        name="country_iso2"
                        label="Land"
                        type="text"
                        :value="$filters['country_iso2'] ?? ''"
                        maxlength="2"
                        placeholder="DE"
                        class="text-uppercase"
                        col-class="col-md-3"
                    />
                    <x-forms.select
                        name="per_page"
                        label="Pro Seite"
                        :options="array_combine([10, 25, 50, 100, 200], [10, 25, 50, 100, 200])"
                        :value="(string)($filters['per_page'] ?? 25)"
                        col-class="col-md-2"
                    />
                </x-filters.filter-form>
            </div>

            <div class="table-responsive">
                <x-ui.data-table striped hover>
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Code</th>
                        <th scope="col">Anzeige</th>
                        <th scope="col">Firma</th>
                        <th scope="col">Kontakt</th>
                        <th scope="col">Adresse</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($senderProfiles as $sender)
                        <tr>
                            <td>#{{ $sender->id()->toInt() }}</td>
                            <td><code>{{ $sender->senderCode() }}</code></td>
                            <td>{{ $sender->displayName() }}</td>
                            <td>{{ $sender->companyName() }}</td>
                            <td>
                                {{ $sender->contactPerson() ?? '—' }}<br>
                                {{ $sender->email() ?? '—' }}<br>
                                {{ $sender->phone() ?? '—' }}
                            </td>
                            <td>
                                {{ $sender->streetName() }} {{ $sender->streetNumber() }}<br>
                                {{ $sender->postalCode() }} {{ $sender->city() }}<br>
                                {{ strtoupper($sender->countryIso2()) }}
                            </td>
                            <td class="text-end">
                                <a
                                    href="{{ route('fulfillment.masterdata.senders.edit', $sender->id()->toInt()) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Bearbeiten
                                </a>
                                <form
                                    action="{{ route('fulfillment.masterdata.senders.destroy', $sender->id()->toInt()) }}"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Senderprofil wirklich löschen?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Noch keine Senderprofile vorhanden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>
    </div>

    <x-ui.pagination-footer :paginator="$paginationLinks" label="Einträgen" />
@endsection
