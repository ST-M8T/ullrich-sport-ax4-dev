@props([
    'orderId' => null,
    'productsUrl' => '',
    'servicesUrl' => '',
    'validateUrl' => '',
    'bookingUrl' => '',
])

@php
    $effectiveBookingUrl = $bookingUrl ?: ($orderId ? route('fulfillment-orders.dhl.book', $orderId) : '#');
    $effectiveProductsUrl = $productsUrl ?: route('api.dhl.products');
    $effectiveServicesUrl = $servicesUrl ?: route('api.dhl.services');
    $effectiveValidateUrl = $validateUrl ?: route('api.dhl.validate-services');
@endphp

<button
    type="button"
    class="btn btn-outline-primary w-100"
    data-dhl-catalog-trigger
    data-order-id="{{ $orderId ?? '' }}"
    data-products-url="{{ $effectiveProductsUrl }}"
    data-services-url="{{ $effectiveServicesUrl }}"
    data-validate-url="{{ $effectiveValidateUrl }}"
    data-booking-url="{{ $effectiveBookingUrl }}"
>
    <i class="bi bi-truck"></i>
    DHL-Produkt wählen
</button>
