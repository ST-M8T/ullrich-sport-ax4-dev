<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Dispatch;

use Illuminate\Foundation\Http\FormRequest;

final class CaptureDispatchScanRequest extends FormRequest
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
            'barcode' => ['required', 'string', 'max:191'],
            'shipment_order_id' => ['sometimes', 'integer', 'min:1'],
            'captured_by_user_id' => ['sometimes', 'integer', 'min:1'],
            'captured_at' => ['sometimes', 'date'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
