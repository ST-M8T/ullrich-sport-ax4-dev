@props([
    'items' => [],
    'colClass' => 'col-md-6',
])

<div class="{{ $colClass }}">
    <dl class="row mb-0">
        @foreach($items as $item)
            <dt class="col-sm-5">{{ $item['label'] }}</dt>
            <dd class="col-sm-7">
                {!! $item['value'] ?? '—' !!}
            </dd>
        @endforeach
    </dl>
</div>

