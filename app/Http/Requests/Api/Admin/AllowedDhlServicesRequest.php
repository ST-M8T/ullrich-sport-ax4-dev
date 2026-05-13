<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Technical input validation for GET /api/admin/dhl/catalog/allowed-services
 * (PROJ-5, §15 — validation at the edge).
 */
final class AllowedDhlServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission gating handled via can:fulfillment.orders.manage middleware.
    }

    /**
     * @return array<string,array<int,mixed>>
     */
    public function rules(): array
    {
        $payerValues = array_map(static fn (DhlPayerCode $c): string => $c->value, DhlPayerCode::cases());

        return [
            'product_code' => ['required', 'string', 'max:3', 'regex:/^[A-Z0-9]{1,3}$/'],
            'from_country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'to_country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'payer_code' => ['required', 'string', Rule::in($payerValues)],
        ];
    }

    public function productCode(): string
    {
        /** @var string $v */
        $v = $this->validated('product_code');
        return $v;
    }

    public function fromCountry(): string
    {
        /** @var string $v */
        $v = $this->validated('from_country');
        return $v;
    }

    public function toCountry(): string
    {
        /** @var string $v */
        $v = $this->validated('to_country');
        return $v;
    }

    public function payerCode(): string
    {
        /** @var string $v */
        $v = $this->validated('payer_code');
        return $v;
    }
}
