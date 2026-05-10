<?php

namespace App\Http\Requests\Fulfillment;

use Illuminate\Foundation\Http\FormRequest;

final class ShipmentOrderManualSyncRequest extends FormRequest
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
            'manual_order_id' => ['required', 'integer', 'min:1'],
            'manual_tracking' => ['nullable', 'string', 'max:191'],
            'manual_sync' => ['nullable', 'in:0,1,true,false'],
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
