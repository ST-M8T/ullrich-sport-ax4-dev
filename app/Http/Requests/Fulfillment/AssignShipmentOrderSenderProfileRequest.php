<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignShipmentOrderSenderProfileRequest extends FormRequest
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
            'sender_profile_id' => [
                'required',
                'integer',
                Rule::exists('fulfillment_sender_profiles', 'id'),
            ],
            'redirect_to' => ['nullable', 'string'],
        ];
    }
}
