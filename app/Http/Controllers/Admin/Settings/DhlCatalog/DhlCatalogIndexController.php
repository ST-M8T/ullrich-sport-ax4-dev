<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogProductListQuery;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlCatalogSyncStatusRepository;
use App\Http\Requests\Admin\Settings\DhlCatalog\DhlCatalogIndexFilterRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Übersichts-Controller des DHL Produktkatalogs (Admin Read-Only).
 *
 * Engineering-Handbuch §7: Presentation — validiert Eingaben, ruft
 * Read-Model-Query und Status-Repository, mappt Ergebnisse zu View-Daten.
 * Keine Fachlogik, keine DB-Queries.
 */
final class DhlCatalogIndexController
{
    public function __construct(
        private readonly DhlCatalogProductListQuery $productListQuery,
        private readonly DhlCatalogSyncStatusRepository $syncStatusRepository,
        private readonly Gate $gate,
    ) {}

    public function index(DhlCatalogIndexFilterRequest $request): View
    {
        // Zweite Verteidigungslinie (Engineering-Handbuch §20): Permission
        // wurde bereits durch Route-Middleware geprüft, hier zusätzlich.
        if (! $this->gate->allows('dhl-catalog.view')) {
            throw new AccessDeniedHttpException;
        }

        $filter = $request->toFilter();
        $products = $this->productListQuery->paginate($filter);
        $syncStatus = $this->syncStatusRepository->get();

        return view('admin.settings.dhl_catalog.index', [
            'products' => $products,
            'productsLinks' => $products->toLinks('admin.settings.dhl.catalog.index', $request->validated()),
            'filter' => [
                'from_country' => $filter->fromCountries,
                'to_country' => $filter->toCountries,
                'status' => $filter->status,
                'source' => $filter->source,
                'q' => $filter->search,
            ],
            'syncStatus' => $syncStatus,
            'canSync' => $this->gate->allows('dhl-catalog.sync'),
            'canViewAudit' => $this->gate->allows('dhl-catalog.audit.read'),
        ]);
    }
}
