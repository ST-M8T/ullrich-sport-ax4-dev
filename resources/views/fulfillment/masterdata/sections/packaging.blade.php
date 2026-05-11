
<section class="card">
    <div class="card-body">
        <x-ui.section-header
            title="Verpackungen"
            description="Transportprofile mit Maßen, Slots und Stack-Informationen."
            :count="$count">
            @if($packagingListUrl)
                <x-slot:actions>
                    <x-ui.action-link :href="$packagingListUrl">
                        Vollständige Liste
                    </x-ui.action-link>
                </x-slot:actions>
            @endif
        </x-ui.section-header>

        @if($previewProfiles->isEmpty())
            <x-ui.empty-state
                title="Keine Verpackungsprofile"
                description="Es wurden keine Verpackungsprofile im Katalog gefunden."
            />
        @else
            <x-ui.data-table dense>
                <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Code</th>
                    <th scope="col">Maße (mm)</th>
                    <th scope="col">Slots</th>
                    <th scope="col">Max / Empfänger</th>
                    <th scope="col">Max / Mix</th>
                    <th scope="col">Stapel (E/M)</th>
                </tr>
                </thead>
                <tbody>
                @foreach($previewProfiles as $profile)
                    <tr>
                        <td>{{ $profile->packageName() }}</td>
                        <td>{{ $profile->packagingCode() ?? '—' }}</td>
                        <td>
                            {{ $profile->lengthMillimetres() }}
                            × {{ $profile->widthMillimetres() }}
                            × {{ $profile->heightMillimetres() }}
                        </td>
                        <td>{{ $profile->truckSlotUnits() }}</td>
                        <td>{{ $profile->maxUnitsPerPalletSameRecipient() }}</td>
                        <td>{{ $profile->maxUnitsPerPalletMixedRecipient() }}</td>
                        <td>
                            {{ $profile->maxStackablePalletsSameRecipient() }}
                            / {{ $profile->maxStackablePalletsMixedRecipient() }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </x-ui.data-table>
        @endif
    </div>
</section>
