<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Queries\DhlCatalogAuditLogQuery;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductServiceAssignmentRepository;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Produkt-Detail-Controller (PROJ-6).
 *
 * §7: Presentation. Lädt Aggregat + zugehörige Assignments + Audit-Snippet
 * über Domain-Ports, mappt zu View-Daten.
 */
final class DhlCatalogProductController
{
    public function __construct(
        private readonly DhlProductRepository $productRepository,
        private readonly DhlProductServiceAssignmentRepository $assignmentRepository,
        private readonly DhlAdditionalServiceRepository $serviceRepository,
        private readonly DhlCatalogAuditLogQuery $auditLogQuery,
        private readonly Gate $gate,
    ) {}

    public function show(string $code): View
    {
        if (! $this->gate->allows('dhl-catalog.view')) {
            throw new AccessDeniedHttpException;
        }

        try {
            $productCode = new DhlProductCode($code);
        } catch (Throwable) {
            throw new NotFoundHttpException;
        }

        $product = $this->productRepository->findByCode($productCode);
        if ($product === null) {
            throw new NotFoundHttpException;
        }

        $assignments = [];
        foreach ($this->assignmentRepository->findByProduct($productCode) as $a) {
            $serviceCode = $a->serviceCode();
            $service = $this->serviceRepository->findByCode($serviceCode);

            $assignments[] = [
                'service_code' => $serviceCode,
                'service_name' => $service?->name() ?? $serviceCode,
                'service_category' => $service?->category()->value ?? 'unknown',
                'from_country' => $a->fromCountry()?->value,
                'to_country' => $a->toCountry()?->value,
                'payer_code' => $a->payerCode()?->value,
                'requirement' => $a->requirement()->value,
                'default_parameters' => $a->defaultParameters(),
                'source' => $a->source()->value,
            ];
        }

        $auditEntries = $this->auditLogQuery->latestForProduct($productCode->value);

        return view('admin.settings.dhl_catalog.products.show', [
            'product' => $product,
            'assignments' => $assignments,
            'auditEntries' => $auditEntries,
            'canViewAudit' => $this->gate->allows('dhl-catalog.audit.read'),
        ]);
    }
}
