@php
    use Illuminate\Support\Facades\Route;

    $assemblyListUrl = Route::has('fulfillment.masterdata.assembly.index')
        ? route('fulfillment.masterdata.assembly.index')
        : null;
@endphp

<section class="card">
    <div class="card-body">
        <x-ui.section-header
            title="Vormontage"
            description="Artikel-Verknüpfungen mit Verpackungen und Gewichten."
            :count="$count">
            @if($assemblyListUrl)
                <x-slot:actions>
                    <x-ui.action-link :href="$assemblyListUrl">
                        Vollständige Liste
                    </x-ui.action-link>
                </x-slot:actions>
            @endif
        </x-ui.section-header>

        @if($assemblyOptions->isEmpty())
            <x-ui.empty-state
                title="Keine Vormontage-Optionen"
                description="Im Katalog wurden keine Vormontage-Datensätze gefunden."
            />
        @else
            <x-ui.data-table dense>
                <thead>
                <tr>
                    <th scope="col">Artikel</th>
                    <th scope="col">Verpackung</th>
                    <th scope="col">Gewicht (kg)</th>
                    <th scope="col">Beschreibung</th>
                </tr>
                </thead>
                <tbody>
                @foreach($processedOptions as $processed)
                    <tr>
                        <td>#{{ $processed['option']->assemblyItemId() }}</td>
                        <td>{{ $processed['packaging']?->packageName() ?? '—' }}</td>
                        <td>{{ $processed['formattedWeight'] }}</td>
                        <td>{{ $processed['option']->description() ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </x-ui.data-table>
        @endif
    </div>
</section>
