
<section class="card">
    <div class="card-body">
        <x-ui.section-header
            title="Varianten"
            description="Default-Zustände, Verpackungen und Montageverweise."
            :count="$count">
            @if($variationsListUrl)
                <x-slot:actions>
                    <x-ui.action-link :href="$variationsListUrl">
                        Vollständige Liste
                    </x-ui.action-link>
                </x-slot:actions>
            @endif
        </x-ui.section-header>

        @if($variationProfiles->isEmpty())
            <x-ui.empty-state
                title="Keine Variantenprofile"
                description="Es liegen keine Variantenkonfigurationen im Katalog vor."
            />
        @else
            <x-ui.data-table dense>
                <thead>
                <tr>
                    <th scope="col">Item</th>
                    <th scope="col">Variation</th>
                    <th scope="col">State</th>
                    <th scope="col">Verpackung</th>
                    <th scope="col">Montage</th>
                    <th scope="col">Gewicht (kg)</th>
                </tr>
                </thead>
                <tbody>
                @foreach($processedProfiles as $processed)
                    <tr>
                        <td>#{{ $processed['profile']->itemId() }}</td>
                        <td>{{ $processed['profile']->variationName() ?? ('Var #' . ($processed['profile']->variationId() ?? '—')) }}</td>
                        <td class="text-uppercase">{{ $processed['profile']->defaultState() }}</td>
                        <td>{{ $processed['packaging']?->packageName() ?? '—' }}</td>
                        <td>{{ $processed['assembly'] ? '#' . $processed['assembly']->assemblyItemId() : '—' }}</td>
                        <td>{{ $processed['formattedWeight'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </x-ui.data-table>
        @endif
    </div>
</section>
