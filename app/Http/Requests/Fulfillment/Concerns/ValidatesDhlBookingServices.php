<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment\Concerns;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlServiceOptionCollection;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\ForbiddenDhlServiceException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\InvalidDhlServiceParameterException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\MissingRequiredDhlServiceException;
use App\Application\Fulfillment\Integrations\Dhl\Exceptions\UnknownDhlServiceException;
use App\Application\Fulfillment\Integrations\Dhl\Mappers\DhlAdditionalServiceMapper;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\RoutingContext;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared validation logic for DHL-booking FormRequests (single + bulk).
 *
 * Engineering-Handbuch:
 *   - §15 Validierung: technische Eingabevalidierung in rules(); fachliche
 *     Invarianten (Service erlaubt fuer Produkt/Routing/Payer, Parameter
 *     erfuellen das Katalog-Schema) ueber den DhlAdditionalServiceMapper.
 *   - §75 DRY: identische Routing-aware Pruefung darf nicht pro
 *     FormRequest dupliziert werden — daher zentralisiert.
 *   - §70 (28) Klare Namen: Trait-Name beschreibt seinen Zweck eindeutig.
 *
 * Voraussetzung: Die nutzende FormRequest stellt die Felder product_code,
 * payer_code, additional_services und (optional) from_country/to_country
 * bereit. Wenn der Routing-Context unvollstaendig ist, wird die fachliche
 * Pruefung uebersprungen — die strukturelle Validierung der rules() bleibt
 * davon unberuehrt.
 */
trait ValidatesDhlBookingServices
{
    /**
     * Returns rules for the additional_services field accepting BOTH the
     * legacy flat-string form ('SVC1') and the new object form
     * ({code: 'X', parameters: {...}}).
     *
     * @return array<string,array<int,string>>
     */
    protected function dhlBookingServiceRules(): array
    {
        return [
            'additional_services' => ['nullable', 'array'],
            'additional_services.*' => ['required'],
            'additional_services.*.code' => ['sometimes', 'string', 'max:50'],
            'additional_services.*.parameters' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Performs routing-aware catalog validation via the
     * DhlAdditionalServiceMapper. Adds per-field errors so the UI can
     * highlight the offending service / parameter.
     */
    protected function validateDhlBookingServices(
        Validator $validator,
        ?string $fromCountry = null,
        ?string $toCountry = null,
    ): void {
        $productCodeRaw = $this->input('product_code');
        $payerCodeRaw = $this->input('payer_code');
        $services = $this->input('additional_services');

        if (! is_string($productCodeRaw) || trim($productCodeRaw) === '') {
            return;
        }
        if (! is_string($payerCodeRaw) || trim($payerCodeRaw) === '') {
            return;
        }
        if (! is_array($services) || $services === []) {
            return;
        }

        try {
            $productCode = DhlProductCode::fromString(strtoupper(trim($productCodeRaw)));
            $payerCode = DhlPayerCode::fromString(strtoupper(trim($payerCodeRaw)));
        } catch (DhlValueObjectException) {
            return; // Structural error already reported by rules().
        }

        $routing = new RoutingContext(
            $fromCountry !== null && trim($fromCountry) !== '' ? strtoupper(trim($fromCountry)) : null,
            $toCountry !== null && trim($toCountry) !== '' ? strtoupper(trim($toCountry)) : null,
            $payerCode,
        );

        // The mapper expects [{code: 'X', parameters: {...}}] or flat strings.
        // DhlServiceOptionCollection::fromArray normalises both shapes.
        $collection = DhlServiceOptionCollection::fromArray(array_values($services));

        /** @var DhlAdditionalServiceMapper $mapper */
        $mapper = app(DhlAdditionalServiceMapper::class);

        try {
            $mapper->toApiPayload($productCode, $routing, $collection);
        } catch (UnknownDhlServiceException $e) {
            $validator->errors()->add(
                'additional_services',
                sprintf('Zusatzleistung "%s" ist im DHL-Katalog nicht bekannt.', $e->serviceCode),
            );
        } catch (ForbiddenDhlServiceException $e) {
            $validator->errors()->add(
                'additional_services',
                sprintf(
                    'Zusatzleistung "%s" ist fuer Produkt "%s" und das gewaehlte Routing nicht zulaessig.',
                    $e->serviceCode,
                    $productCode->value,
                ),
            );
        } catch (MissingRequiredDhlServiceException $e) {
            $validator->errors()->add(
                'additional_services',
                sprintf(
                    'Pflicht-Zusatzleistung(en) fehlen fuer Produkt "%s": %s.',
                    $productCode->value,
                    implode(', ', $e->missingCodes),
                ),
            );
        } catch (InvalidDhlServiceParameterException $e) {
            $validator->errors()->add(
                sprintf('additional_services.%s.parameters.%s', $e->serviceCode, $e->parameterPath),
                sprintf(
                    'Parameter "%s" fuer Zusatzleistung "%s" verletzt das Katalog-Schema: %s',
                    $e->parameterPath,
                    $e->serviceCode,
                    $e->schemaViolation,
                ),
            );
        } catch (\Throwable) {
            // Catalog not populated or transient infra issue — do not block
            // booking with a generic 422. Domain-level mapper handles the
            // strict/non-strict policy at runtime (PROJ-3).
        }
    }
}
