@props(['order'])

@if($order->isBooked())
    <span class="badge bg-success">Gebucht</span>
@else
    <span class="badge bg-warning text-dark">Offen</span>
@endif
@if($order->bookedAt())
    <br><small class="text-muted">am {{ $order->bookedAt()?->format('d.m.Y H:i') }}</small>
    @if($order->bookedBy())
        <br><small class="text-muted">von {{ $order->bookedBy() }}</small>
    @endif
@endif
@if($order->dhlShipmentId())
    <br><span class="badge bg-info text-dark mt-1">DHL: {{ $order->dhlShipmentId() }}</span>
    @if($order->dhlBookedAt())
        <br><small class="text-muted">DHL gebucht: {{ $order->dhlBookedAt()?->format('d.m.Y H:i') }}</small>
    @endif
@endif
@if($order->dhlBookingError())
    <br><span class="badge bg-danger mt-1">DHL Fehler</span>
@endif

