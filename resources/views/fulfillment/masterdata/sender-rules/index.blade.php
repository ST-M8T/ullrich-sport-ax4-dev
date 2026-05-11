@extends('layouts.admin', [
    'pageTitle' => 'Sender-Regeln',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Regeln'],
    ],
])


@section('content')
    <x-ui.page-header title="Sender-Regeln" subtitle="Automatische Steuerung des neutralen Versenders auf Basis definierter Kriterien.">
        <x-slot:actions>
            <a href="{{ route('fulfillment.masterdata.sender-rules.create') }}" class="btn btn-primary">
            Neue Regel
        </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card">
        <div class="card-body p-0">
            <div class="p-3 border-bottom bg-light-subtle">
                <x-filters.filter-form :action="route('fulfillment.masterdata.sender-rules.index')" :filters="$filters ?? []">
                    <x-forms.select
                        name="target_sender_id"
                        label="Sender"
                        :options="collect($senderById)->mapWithKeys(fn($profile) => [$profile->id()->toInt() => $profile->displayName()])->prepend('Alle Sender', '')->all()"
                        :value="(string)($filters['target_sender_id'] ?? '')"
                        col-class="col-lg-3"
                    />
                    <x-forms.select
                        name="rule_type"
                        label="Regeltyp"
                        :options="collect($ruleTypes)->prepend('Alle Typen', '')->all()"
                        :value="$filters['rule_type'] ?? ''"
                        col-class="col-lg-3"
                    />
                    <x-forms.select
                        name="is_active"
                        label="Status"
                        :options="['' => 'Aktiv & inaktiv', '1' => 'Aktiv', '0' => 'Inaktiv']"
                        :value="(string)($filters['is_active'] ?? '')"
                        col-class="col-lg-2"
                    />
                    <x-forms.input
                        name="search"
                        label="Suchbegriff"
                        type="search"
                        :value="$filters['search'] ?? ''"
                        placeholder="Match-Wert…"
                        col-class="col-lg-2"
                    />
                    <x-forms.select
                        name="per_page"
                        label="Pro Seite"
                        :options="array_combine([10, 25, 50, 100, 200], [10, 25, 50, 100, 200])"
                        :value="(string)($filters['per_page'] ?? 25)"
                        col-class="col-lg-2"
                    />
                </x-filters.filter-form>
            </div>

            <div class="table-responsive">
                <x-ui.data-table striped hover>
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Priorität</th>
                        <th scope="col">Typ</th>
                        <th scope="col">Wert</th>
                        <th scope="col">Sender</th>
                        <th scope="col">Status</th>
                        <th scope="col">Beschreibung</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rules as $rule)
                        @php
                            $sender = $senderById->get($rule->targetSenderId()->toInt());
                            $typeLabel = $ruleTypes[$rule->ruleType()] ?? $rule->ruleType();
                        @endphp
                        <tr>
                            <td>#{{ $rule->id()->toInt() }}</td>
                            <td>{{ $rule->priority() }}</td>
                            <td>{{ $typeLabel }}</td>
                            <td><code>{{ $rule->matchValue() }}</code></td>
                            <td>{{ $sender?->displayName() ?? '—' }}</td>
                            <td>
                                @if($rule->isActive())
                                    <span class="badge bg-success">Aktiv</span>
                                @else
                                    <span class="badge bg-secondary">Inaktiv</span>
                                @endif
                            </td>
                            <td>{{ $rule->description() ?? '—' }}</td>
                            <td class="text-end">
                                <a
                                    href="{{ route('fulfillment.masterdata.sender-rules.edit', $rule->id()->toInt()) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Bearbeiten
                                </a>
                                <form
                                    action="{{ route('fulfillment.masterdata.sender-rules.destroy', $rule->id()->toInt()) }}"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Regel wirklich löschen?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Noch keine Sender-Regeln definiert.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>
    </div>

    <x-ui.pagination-footer :paginator="$paginationLinks" label="Einträgen" />
@endsection
