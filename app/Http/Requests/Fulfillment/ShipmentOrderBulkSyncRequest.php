<?php

namespace App\Http\Requests\Fulfillment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShipmentOrderBulkSyncRequest extends FormRequest
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
            'scope' => ['nullable', Rule::in(['page', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'filter' => ['nullable', Rule::in(['recent', 'booked', 'unbooked'])],
            'search' => ['nullable', 'string', 'max:191'],
            'sort' => ['nullable', Rule::in([
                'processed_at',
                'order_id',
                'kunden_id',
                'email',
                'country',
                'rechbetrag',
                'booked_at',
                'tracking_number',
            ])],
            'dir' => ['nullable', Rule::in(['ASC', 'DESC', 'asc', 'desc'])],
            'sender_code' => ['nullable', 'string', 'max:64'],
            'destination_country' => ['nullable', 'string', 'size:2'],
            'is_booked' => ['nullable', 'in:0,1'],
            'processed_from' => ['nullable', 'date_format:Y-m-d'],
            'processed_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function validated($key = null, $default = null)
    {
        /** @var array<string, mixed> $validated */
        $validated = parent::validated($key, $default);

        return collect($validated)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
    }
}
