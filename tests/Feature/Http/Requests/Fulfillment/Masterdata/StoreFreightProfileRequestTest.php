<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests\Fulfillment\Masterdata;

use App\Http\Requests\Fulfillment\Masterdata\StoreFreightProfileRequest;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentFreightProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog\DhlCatalogControllerTestHelpers;
use Tests\TestCase;

/**
 * Validation contract for {@see StoreFreightProfileRequest}.
 *
 * Engineering-Handbuch §15: technische Eingabevalidierung am Rand.
 * Tests the rules() composition (base + freight fields + DHL catalog) plus the
 * ValidatesDhlCatalogProfile trait integration through the HTTP boundary.
 */
final class StoreFreightProfileRequestTest extends TestCase
{
    use DhlCatalogControllerTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    public function test_rules_array_exposes_expected_keys(): void
    {
        $request = new StoreFreightProfileRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('shipping_profile_id', $rules);
        $this->assertArrayHasKey('label', $rules);
        $this->assertArrayHasKey('dhl_product_id', $rules);
        $this->assertArrayHasKey('dhl_product_code', $rules);
        $this->assertArrayHasKey('dhl_default_service_parameters', $rules);
        $this->assertContains('required', $rules['shipping_profile_id']);
        $this->assertContains('integer', $rules['shipping_profile_id']);
    }

    public function test_store_rejects_missing_shipping_profile_id(): void
    {
        $response = $this->post(route('fulfillment.masterdata.freight.store'), [
            'label' => 'Profil ohne ID',
        ]);

        $response->assertSessionHasErrors(['shipping_profile_id']);
    }

    public function test_store_rejects_duplicate_shipping_profile_id(): void
    {
        FulfillmentFreightProfileModel::create([
            'shipping_profile_id' => 9001,
            'label' => 'Existing',
        ]);

        $response = $this->post(route('fulfillment.masterdata.freight.store'), [
            'shipping_profile_id' => 9001,
            'label' => 'Duplicate',
        ]);

        $response->assertSessionHasErrors(['shipping_profile_id']);
    }

    public function test_store_rejects_label_exceeding_max_length(): void
    {
        $response = $this->post(route('fulfillment.masterdata.freight.store'), [
            'shipping_profile_id' => 9002,
            'label' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors(['label']);
    }

    public function test_store_accepts_minimal_valid_payload(): void
    {
        $response = $this->post(route('fulfillment.masterdata.freight.store'), [
            'shipping_profile_id' => 9003,
            'label' => 'Standard-Versand DHL',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fulfillment_freight_profiles', [
            'shipping_profile_id' => 9003,
            'label' => 'Standard-Versand DHL',
        ]);
    }

    public function test_store_rejects_unknown_dhl_product_code_via_trait(): void
    {
        $response = $this->post(route('fulfillment.masterdata.freight.store'), [
            'shipping_profile_id' => 9004,
            'label' => 'Test',
            'dhl_product_code' => 'NOPE',
        ]);

        $response->assertSessionHasErrors(['dhl_product_code']);
    }

    public function test_store_accepts_known_dhl_product_code_via_trait(): void
    {
        $this->createProduct('ECI');

        $response = $this->post(route('fulfillment.masterdata.freight.store'), [
            'shipping_profile_id' => 9005,
            'label' => 'Express',
            'dhl_product_code' => 'ECI',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fulfillment_freight_profiles', [
            'shipping_profile_id' => 9005,
            'dhl_product_code' => 'ECI',
        ]);
    }
}
