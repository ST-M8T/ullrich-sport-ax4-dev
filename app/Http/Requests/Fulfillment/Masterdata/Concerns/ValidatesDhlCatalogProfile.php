<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Masterdata\Concerns;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\InvalidParameterException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared validation rules + cross-field checks for FreightProfile FormRequests
 * (Store + Update). Lives in `Http/Requests/Concerns` because it concerns
 * presentation-layer input validation only.
 *
 * Engineering-Handbuch §15: technische Eingabevalidierung am Rand;
 * fachliche Invarianten (Produkt existiert, Service existiert, Parameter
 * erfüllen das JSON-Schema) werden gegen den DHL-Katalog (Domain-Repository)
 * geprüft. Routing-gebundene Checks (forbidden / required) leben im Booking-
 * Flow — Masterdata-Profile haben keine Routing-Achsen.
 * Engineering-Handbuch §75 (DRY): identische Regeln/Logik nur an einer
 * Stelle — hier zentralisiert für Store + Update.
 */
trait ValidatesDhlCatalogProfile
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function dhlCatalogRules(): array
    {
        return [
            'dhl_product_code' => ['sometimes', 'nullable', 'string', 'max:8'],
            'dhl_default_service_parameters' => ['sometimes', 'nullable', 'array', 'max:50'],
            'dhl_default_service_parameters.*' => ['array'],
            'dhl_default_service_parameters.*.code' => ['required_with:dhl_default_service_parameters.*', 'string', 'max:8'],
            'dhl_default_service_parameters.*.parameters' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $code = $this->input('dhl_product_code');
            $code = is_string($code) && trim($code) !== '' ? strtoupper(trim($code)) : null;

            if ($code !== null) {
                $product = $this->resolveDhlProduct($code);
                if ($product === null) {
                    $v->errors()->add(
                        'dhl_product_code',
                        sprintf('Das DHL-Produkt "%s" existiert nicht im Katalog.', $code),
                    );

                    return;
                }

                // Deprecated product: NOT a validation error — only a session flash.
                if ($product->isDeprecated()) {
                    $successor = $product->replacedByCode()?->value;
                    $message = $successor !== null
                        ? sprintf(
                            'Hinweis: DHL-Produkt "%s" ist abgekündigt und wurde durch "%s" ersetzt.',
                            $code,
                            $successor,
                        )
                        : sprintf('Hinweis: DHL-Produkt "%s" ist abgekündigt.', $code);
                    /** @phpstan-ignore-next-line session helper available in HTTP context */
                    session()->flash('warning', $message);
                }
            }

            $serviceParameters = $this->input('dhl_default_service_parameters');
            if ($code === null || ! is_array($serviceParameters) || $serviceParameters === []) {
                return;
            }

            /** @var DhlAdditionalServiceRepository $serviceRepo */
            $serviceRepo = app(DhlAdditionalServiceRepository::class);

            // A FreightProfile is master-data — no routing axes (from, to,
            // payer). The mapper's assignment-driven `forbidden`/`required`
            // checks therefore cannot fire here; the catalog port for
            // routing-bound checks lives in the booking flow. We DO enforce
            // service-code existence and parameter-schema conformance, which
            // are routing-independent invariants.
            foreach ($serviceParameters as $index => $entry) {
                if (! is_array($entry) || ! isset($entry['code'])) {
                    continue;
                }
                $serviceCode = (string) $entry['code'];

                $service = $serviceRepo->findByCode($serviceCode);
                if ($service === null) {
                    $v->errors()->add(
                        'dhl_default_service_parameters',
                        sprintf('Service-Code "%s" ist im DHL-Katalog nicht bekannt.', $serviceCode),
                    );

                    continue;
                }

                $parameters = is_array($entry['parameters'] ?? null) ? $entry['parameters'] : [];
                if ($parameters === []) {
                    continue;
                }

                try {
                    $service->validateParameters($parameters);
                } catch (InvalidParameterException $e) {
                    $v->errors()->add(
                        sprintf(
                            'dhl_default_service_parameters.%d.parameters.%s',
                            (int) $index,
                            $e->path,
                        ),
                        sprintf(
                            'Parameter "%s" für Service "%s" verletzt das Katalog-Schema: %s',
                            $e->path,
                            $serviceCode,
                            $e->reason,
                        ),
                    );
                }
            }
        });
    }

    private function resolveDhlProduct(string $code): ?\App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct
    {
        try {
            $productCode = DhlProductCode::fromString($code);
        } catch (DhlValueObjectException) {
            return null;
        }

        /** @var DhlProductRepository $repository */
        $repository = app(DhlProductRepository::class);

        return $repository->findByCode($productCode);
    }
}
