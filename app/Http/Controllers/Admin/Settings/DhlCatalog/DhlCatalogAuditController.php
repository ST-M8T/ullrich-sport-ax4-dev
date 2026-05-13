<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogAuditLogQuery;
use App\Http\Requests\Admin\Settings\DhlCatalog\DhlCatalogAuditFilterRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Audit-Log Controller (PROJ-6 / t15b).
 *
 * Engineering-Handbuch §7: nimmt validierte Filter entgegen, ruft die
 * Read-Model-Query und reicht das paginierte Ergebnis an die View weiter.
 * Keine Fachlogik, keine DB-Queries.
 */
final class DhlCatalogAuditController
{
    public function __construct(
        private readonly DhlCatalogAuditLogQuery $auditLogQuery,
        private readonly Gate $gate,
    ) {}

    public function index(DhlCatalogAuditFilterRequest $request): View
    {
        // Zweite Verteidigungslinie (Engineering-Handbuch §20).
        if (! $this->gate->allows('dhl-catalog.audit.read')) {
            throw new AccessDeniedHttpException;
        }

        $filter = $request->toFilter();
        $entries = $this->auditLogQuery->paginate($filter);

        return view('admin.settings.dhl_catalog.audit.index', [
            'entries' => $entries,
            'entriesLinks' => $entries->toLinks(
                'admin.settings.dhl.catalog.audit.index',
                $request->validated(),
            ),
            'filter' => [
                'from' => $filter->from?->format('Y-m-d\TH:i'),
                'to' => $filter->to?->format('Y-m-d\TH:i'),
                'entity_type' => $filter->entityType,
                'action' => $filter->action,
                'actor' => $filter->actor,
            ],
        ]);
    }
}
