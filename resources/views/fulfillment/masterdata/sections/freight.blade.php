@php
    use Illuminate\Support\Facades\Route;

    $freightListUrl = Route::has('fulfillment.masterdata.freight.index')
        ? route('fulfillment.masterdata.freight.index')
        : null;
@endphp

<section class="card">
    <div class="card-body">
        <x-ui.section-header
            title="Freight-Profile"
            description="Shipping-Profile IDs mit Anzeigenamen."
            :count="$count">
            @if($freightListUrl)
                <x-slot:actions>
                    <x-ui.action-link :href="$freightListUrl">
                        Vollständige Liste
                    </x-ui.action-link>
                </x-slot:actions>
            @endif
        </x-ui.section-header>

        @if($freightProfiles->isEmpty())
            <x-ui.empty-state
                title="Keine Freight-Profile"
                description="Der Katalog enthält keine Freight-Profile."
            />
        @else
            <div class="table-responsive">
                <x-ui.data-table dense>
                    <thead>
                    <tr>
                        <th scope="col">Shipping Profile ID</th>
                        <th scope="col">Label</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($freightProfiles as $profile)
                        <tr>
                            <td>#{{ $profile->shippingProfileId()->toInt() }}</td>
                            <td>{{ $profile->label() ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </x-ui.data-table>
            </div>
        @endif
    </div>
</section>
