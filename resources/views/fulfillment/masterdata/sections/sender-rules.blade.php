@php
    use Illuminate\Support\Facades\Route;

    $senderRulesUrl = Route::has('fulfillment.masterdata.sender-rules.index')
        ? route('fulfillment.masterdata.sender-rules.index')
        : null;
@endphp

<section class="card">
    <div class="card-body">
        <x-ui.section-header
            title="Sender-Regeln"
            description="Routing- und Matching-Regeln mit Prioritäten."
            :count="$count">
            @if($senderRulesUrl)
                <x-slot:actions>
                    <x-ui.action-link :href="$senderRulesUrl">
                        Vollständige Liste
                    </x-ui.action-link>
                </x-slot:actions>
            @endif
        </x-ui.section-header>

        @if($senderRules->isEmpty())
            <x-ui.empty-state
                title="Keine Regeln"
                description="Es wurden keine Sender-Regeln hinterlegt."
            />
        @else
            <div class="table-responsive">
                <x-ui.data-table dense>
                    <thead>
                    <tr>
                        <th scope="col">Priorität</th>
                        <th scope="col">Typ</th>
                        <th scope="col">Match</th>
                        <th scope="col">Ziel-Sender</th>
                        <th scope="col">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($processedRules as $processed)
                        <tr>
                            <td>{{ $processed['rule']->priority() }}</td>
                            <td>{{ $processed['formattedRuleType'] }}</td>
                            <td><code>{{ $processed['rule']->matchValue() }}</code></td>
                            <td>{{ $processed['targetSender']?->displayName() ?? ('#' . $processed['rule']->targetSenderId()->toInt()) }}</td>
                            <td>
                                <span class="badge {{ $processed['statusBadgeClass'] }}">
                                    {{ $processed['statusLabel'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </x-ui.data-table>
            </div>
        @endif
    </div>
</section>
