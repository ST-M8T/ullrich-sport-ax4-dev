@props([
    'items' => [],
    'colClass' => 'col-md-6',
])

<div class="{{ $colClass }}">
    <dl class="row mb-0">
        @foreach($items as $item)
            <dt class="col-sm-5">{{ $item['label'] }}</dt>
            <dd class="col-sm-7">
                @php($value = $item['value'] ?? '—')

                @if($value instanceof \Illuminate\Contracts\View\View)
                    {!! $value->render() !!}
                @elseif($value instanceof \Illuminate\Contracts\Support\Htmlable)
                    {!! $value->toHtml() !!}
                @else
                    {{ $value }}
                @endif
            </dd>
        @endforeach
    </dl>
</div>
