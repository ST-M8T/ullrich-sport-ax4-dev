<?php

namespace App\Http\Requests\Fulfillment\Masterdata;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSenderProfileRequest extends FormRequest
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
        $profileId = $this->route('senderProfile') ?? $this->route('sender_profile');

        return [
            'sender_code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('fulfillment_sender_profiles', 'sender_code')->ignore($profileId),
            ],
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'street_name' => ['sometimes', 'required', 'string', 'max:255'],
            'street_number' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address_addition' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'required', 'string', 'max:32'],
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'country_iso2' => ['sometimes', 'required', 'string', 'size:2'],
        ];
    }
}
