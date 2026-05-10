<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment;

use Illuminate\Foundation\Http\FormRequest;

final class DhlBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'string', 'max:64'],
            'additional_services' => ['nullable', 'array'],
            'additional_services.*' => ['string', 'max:64'],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
