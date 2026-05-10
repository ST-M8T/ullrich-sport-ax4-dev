<?php

namespace App\Http\Requests\Fulfillment;

use Illuminate\Foundation\Http\FormRequest;

final class ShipmentOrderTrackingTransferRequest extends FormRequest
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
            'tracking_number' => ['nullable', 'string', 'max:191'],
            'sync_immediately' => ['nullable', 'boolean'],
            'redirect_to' => ['nullable', 'url'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if (array_key_exists('sync_immediately', $validated)) {
            $validated['sync_immediately'] = (bool) (int) $validated['sync_immediately'];
        }

        if (isset($validated['tracking_number']) && is_string($validated['tracking_number'])) {
            $validated['tracking_number'] = trim($validated['tracking_number']);
        }

        return $validated;
    }
}
