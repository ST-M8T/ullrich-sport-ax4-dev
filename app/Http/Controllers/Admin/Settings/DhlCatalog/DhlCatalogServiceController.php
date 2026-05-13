<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service-Detail-Controller (PROJ-6).
 *
 * Zeigt Stammdaten + Parameter-Schema-Preview eines Additional Service.
 * Das Flattening des JSON-Schemas erfolgt in der Domain
 * ({@see \App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema::toArray()})
 * bzw. im View-Helper; der Controller transportiert nur das Roh-Array.
 */
final class DhlCatalogServiceController
{
    public function __construct(
        private readonly DhlAdditionalServiceRepository $serviceRepository,
        private readonly Gate $gate,
    ) {}

    public function show(string $code): View
    {
        if (! $this->gate->allows('dhl-catalog.view')) {
            throw new AccessDeniedHttpException;
        }

        $service = $this->serviceRepository->findByCode($code);
        if ($service === null) {
            throw new NotFoundHttpException;
        }

        return view('admin.settings.dhl_catalog.services.show', [
            'service' => $service,
            'parameterSchema' => $service->parameterSchema()->toArray(),
        ]);
    }
}
