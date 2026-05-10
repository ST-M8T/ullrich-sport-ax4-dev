<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSenderProfileRequest extends FormRequest
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
            'sender_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('fulfillment_sender_profiles', 'sender_code'),
            ],
            'display_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'street_name' => ['required', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:32'],
            'address_addition' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'city' => ['required', 'string', 'max:255'],
            'country_iso2' => ['required', 'string', 'size:2'],
        ];
    }
}
