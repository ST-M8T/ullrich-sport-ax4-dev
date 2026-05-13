<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Technical input validation for POST
 * /api/admin/dhl/catalog/allowed-services/intersection (PROJ-5 bulk path).
 *
 * Max 100 routings per request (Engineering-Handbuch §22 — bounded inputs at
 * the API edge). Larger batches must be split client-side.
 */
final class AllowedDhlServicesIntersectionRequest extends FormRequest
{
    public const MAX_ROUTINGS = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,array<int,mixed>>
     */
    public function rules(): array
    {
        $payerValues = array_map(static fn (DhlPayerCode $c): string => $c->value, DhlPayerCode::cases());

        return [
            'routings' => ['required', 'array', 'min:1', 'max:' . self::MAX_ROUTINGS],
            'routings.*.product_code' => ['required', 'string', 'max:3', 'regex:/^[A-Z0-9]{1,3}$/'],
            'routings.*.from_country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'routings.*.to_country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'routings.*.payer_code' => ['required', 'string', Rule::in($payerValues)],
        ];
    }

    /**
     * @return list<array{product_code:string,from_country:string,to_country:string,payer_code:string}>
     */
    public function routings(): array
    {
        /** @var list<array{product_code:string,from_country:string,to_country:string,payer_code:string}> $v */
        $v = $this->validated('routings');
        return $v;
    }
}
