<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings\DhlCatalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogProductListFilter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request für die Katalog-Übersicht (PROJ-6).
 *
 * Engineering-Handbuch §15: technische Eingabevalidierung am Rand.
 * Permission-Check läuft separat via Route-Middleware (`can:dhl-catalog.view`).
 */
final class DhlCatalogIndexFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-Middleware enforces this; FormRequest only validates payload.
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'from_country' => ['sometimes', 'array', 'max:20'],
            'from_country.*' => ['string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'to_country' => ['sometimes', 'array', 'max:20'],
            'to_country.*' => ['string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,deprecated'],
            'source' => ['sometimes', 'nullable', 'string', 'in:seed,api,manual'],
            'q' => ['sometimes', 'nullable', 'string', 'max:64'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function toFilter(): DhlCatalogProductListFilter
    {
        $from = array_values(array_map(
            static fn (string $c): string => strtoupper($c),
            (array) $this->input('from_country', []),
        ));
        $to = array_values(array_map(
            static fn (string $c): string => strtoupper($c),
            (array) $this->input('to_country', []),
        ));

        $statusRaw = $this->input('status');
        $sourceRaw = $this->input('source');
        $qRaw = $this->input('q');

        return new DhlCatalogProductListFilter(
            fromCountries: $from,
            toCountries: $to,
            status: is_string($statusRaw) && $statusRaw !== '' ? $statusRaw : null,
            source: is_string($sourceRaw) && $sourceRaw !== '' ? $sourceRaw : null,
            search: is_string($qRaw) && trim($qRaw) !== '' ? trim($qRaw) : null,
            page: max(1, (int) $this->input('page', 1)),
        );
    }
}
