
<section class="card">
    <div class="card-body">
        <x-ui.section-header
            title="Sender"
            description="Absenderprofile inklusive Adresse und Kontakt."
            :count="$count">
            @if($senderListUrl)
                <x-slot:actions>
                    <x-ui.action-link :href="$senderListUrl">
                        Vollständige Liste
                    </x-ui.action-link>
                </x-slot:actions>
            @endif
        </x-ui.section-header>

        @if($senderProfiles->isEmpty())
            <x-ui.empty-state
                title="Keine Senderprofile"
                description="Im Katalog wurden keine Absender gefunden."
            />
        @else
            <div class="table-responsive">
                <x-ui.data-table dense>
                    <thead>
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Name</th>
                        <th scope="col">Kontakt</th>
                        <th scope="col">Adresse</th>
                        <th scope="col">Land</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($processedSenders as $processed)
                        <tr>
                            <td><code>{{ $processed['sender']->senderCode() }}</code></td>
                            <td>{{ $processed['sender']->displayName() }}</td>
                            <td>
                                {{ $processed['contactInfo'] }}<br>
                                <span class="text-muted small">{{ $processed['contactDetail'] }}</span>
                            </td>
                            <td>
                                {{ $processed['addressLine1'] }}<br>
                                {{ $processed['addressLine2'] }}
                            </td>
                            <td>{{ $processed['sender']->countryIso2() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </x-ui.data-table>
            </div>
        @endif
    </div>
</section>
