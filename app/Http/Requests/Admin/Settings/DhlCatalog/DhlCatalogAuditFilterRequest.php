<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings\DhlCatalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogAuditLogFilter;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request für die Audit-Log-Ansicht (PROJ-6).
 */
final class DhlCatalogAuditFilterRequest extends FormRequest
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
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'in:product,service,assignment'],
            'action' => ['sometimes', 'nullable', 'string', 'in:created,updated,deprecated,restored,deleted'],
            'actor' => ['sometimes', 'nullable', 'string', 'max:128'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function toFilter(): DhlCatalogAuditLogFilter
    {
        $fromRaw = $this->input('from');
        $toRaw = $this->input('to');
        $entityType = $this->input('entity_type');
        $action = $this->input('action');
        $actor = $this->input('actor');

        return new DhlCatalogAuditLogFilter(
            from: is_string($fromRaw) && $fromRaw !== '' ? new DateTimeImmutable($fromRaw) : null,
            to: is_string($toRaw) && $toRaw !== '' ? new DateTimeImmutable($toRaw) : null,
            entityType: is_string($entityType) && $entityType !== '' ? $entityType : null,
            action: is_string($action) && $action !== '' ? $action : null,
            actor: is_string($actor) && trim($actor) !== '' ? trim($actor) : null,
            page: max(1, (int) $this->input('page', 1)),
        );
    }
}
