<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests\Fulfillment\Masterdata;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentFreightProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogControllerTestHelpers;
use Tests\TestCase;

/**
 * Validation contract of {@see \App\Http\Requests\Fulfillment\Masterdata\UpdateFreightProfileRequest}
 * and {@see \App\Http\Requests\Fulfillment\Masterdata\StoreFreightProfileRequest} after the
 * DHL-catalog FK was introduced (PROJ-4 / t23b).
 *
 * Engineering-Handbuch §15 — input validation at the edge, against the
 * Application/Domain catalog port.
 */
final class FreightProfileDhlCatalogValidationTest extends TestCase
{
    use DhlCatalogControllerTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    private function freightProfile(int $id = 555): FulfillmentFreightProfileModel
    {
        return FulfillmentFreightProfileModel::create([
            'shipping_profile_id' => $id,
            'label' => 'Test',
        ]);
    }

    public function test_update_accepts_known_catalog_product_without_services(): void
    {
        $this->freightProfile(101);
        $this->createProduct('ECI');

        $response = $this->put(route('fulfillment.masterdata.freight.update', 101), [
            'dhl_product_code' => 'ECI',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fulfillment_freight_profiles', [
            'shipping_profile_id' => 101,
            'dhl_product_code' => 'ECI',
        ]);
    }

    public function test_update_rejects_unknown_dhl_product_code(): void
    {
        $this->freightProfile(102);

        $response = $this->put(route('fulfillment.masterdata.freight.update', 102), [
            'dhl_product_code' => 'XYZ',
        ]);

        $response->assertSessionHasErrors(['dhl_product_code']);
        $this->assertDatabaseMissing('fulfillment_freight_profiles', [
            'shipping_profile_id' => 102,
            'dhl_product_code' => 'XYZ',
        ]);
    }

    public function test_update_rejects_unknown_service_code(): void
    {
        $this->freightProfile(103);
        $this->createProduct('ECI');

        $response = $this->put(route('fulfillment.masterdata.freight.update', 103), [
            'dhl_product_code' => 'ECI',
            'dhl_default_service_parameters' => [
                ['code' => 'ZZZ'],
            ],
        ]);

        $response->assertSessionHasErrors(['dhl_default_service_parameters']);
    }

    public function test_update_rejects_invalid_parameter_against_service_schema(): void
    {
        $this->freightProfile(104);
        $this->createProduct('ECI');
        // Service whose JSON-Schema only accepts an integer `weight_kg`.
        $this->createService('TLF', [
            'parameter_schema' => [
                'type' => 'object',
                'properties' => [
                    'weight_kg' => ['type' => 'integer'],
                ],
                'required' => ['weight_kg'],
                'additionalProperties' => false,
            ],
        ]);

        $response = $this->put(route('fulfillment.masterdata.freight.update', 104), [
            'dhl_product_code' => 'ECI',
            'dhl_default_service_parameters' => [
                ['code' => 'TLF', 'parameters' => ['weight_kg' => 'not-a-number']],
            ],
        ]);

        $response->assertSessionHasErrors();
        $errors = session('errors')->getBag('default')->toArray();
        $this->assertNotEmpty(
            array_filter(
                array_keys($errors),
                static fn (string $key): bool => str_starts_with($key, 'dhl_default_service_parameters'),
            ),
            'Expected at least one error keyed under dhl_default_service_parameters.*',
        );
    }

    public function test_update_accepts_deprecated_product_with_warning_flash(): void
    {
        $this->freightProfile(105);
        $this->createProduct('OLD', [
            'deprecated_at' => '2024-01-01 00:00:00',
            'replaced_by_code' => null,
        ]);

        $response = $this->put(route('fulfillment.masterdata.freight.update', 105), [
            'dhl_product_code' => 'OLD',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('warning');
        $this->assertDatabaseHas('fulfillment_freight_profiles', [
            'shipping_profile_id' => 105,
            'dhl_product_code' => 'OLD',
        ]);
    }

    public function test_update_without_dhl_fields_still_works(): void
    {
        $this->freightProfile(106);

        $response = $this->put(route('fulfillment.masterdata.freight.update', 106), [
            'label' => 'Erneuert',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fulfillment_freight_profiles', [
            'shipping_profile_id' => 106,
            'label' => 'Erneuert',
        ]);
    }

    public function test_store_validates_catalog_product_code(): void
    {
        $response = $this->post(route('fulfillment.masterdata.freight.store'), [
            'shipping_profile_id' => 4242,
            'label' => 'Neues Profil',
            'dhl_product_code' => 'ZZZ',
        ]);

        $response->assertSessionHasErrors(['dhl_product_code']);
    }
}
