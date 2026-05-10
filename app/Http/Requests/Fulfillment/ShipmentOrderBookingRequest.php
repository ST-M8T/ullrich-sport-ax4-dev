<?php

namespace App\Http\Requests\Fulfillment;

use Illuminate\Foundation\Http\FormRequest;

final class ShipmentOrderBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'redirect_to' => ['nullable', 'url'],
        ];
    }
}
