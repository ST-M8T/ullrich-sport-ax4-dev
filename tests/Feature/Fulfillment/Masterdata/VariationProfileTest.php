<?php

namespace Tests\Feature\Fulfillment\Masterdata;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentAssemblyOptionModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentVariationProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VariationProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    private function createPackaging(string $code = 'PKG-VAR'): FulfillmentPackagingProfileModel
    {
        return FulfillmentPackagingProfileModel::create([
            'package_name' => 'Var Verpackung '.$code,
            'packaging_code' => $code,
            'length_mm' => 1200,
            'width_mm' => 800,
            'height_mm' => 600,
            'truck_slot_units' => 1,
            'max_units_per_pallet_same_recipient' => 10,
            'max_units_per_pallet_mixed_recipient' => 10,
            'max_stackable_pallets_same_recipient' => 2,
            'max_stackable_pallets_mixed_recipient' => 1,
            'notes' => null,
        ]);
    }

    private function createAssembly(FulfillmentPackagingProfileModel $packaging): FulfillmentAssemblyOptionModel
    {
        return FulfillmentAssemblyOptionModel::create([
            'assembly_item_id' => 5001,
            'assembly_packaging_id' => $packaging->getKey(),
            'assembly_weight_kg' => 5.0,
            'description' => 'Assembly Option',
        ]);
    }

    public function test_index_displays_variation_profiles(): void
    {
        $packaging = $this->createPackaging('PKG-INDEX');

        FulfillmentVariationProfileModel::create([
            'item_id' => 100,
            'variation_id' => 200,
            'variation_name' => 'Test Variation',
            'default_state' => 'assembled',
            'default_packaging_id' => $packaging->getKey(),
            'default_weight_kg' => 15.5,
            'assembly_option_id' => null,
        ]);

        $response = $this->get(route('fulfillment.masterdata.variations.index'));

        $response->assertOk();
        $response->assertSee('Test Variation');
        $response->assertSee('100');
    }

    public function test_can_create_variation_profile(): void
    {
        $packaging = $this->createPackaging('PKG-CREATE');
        $assembly = $this->createAssembly($packaging);

        $payload = [
            'item_id' => 111,
            'variation_id' => 222,
            'variation_name' => 'Neue Variation',
            'default_state' => 'kit',
            'default_packaging_id' => $packaging->getKey(),
            'default_weight_kg' => 9.8,
            'assembly_option_id' => $assembly->getKey(),
        ];

        $response = $this->post(route('fulfillment.masterdata.variations.store'), $payload);

        $response->assertRedirect(route('fulfillment.masterdata.variations.index'));
        $this->assertDatabaseHas('fulfillment_variation_profiles', [
            'item_id' => 111,
            'variation_id' => 222,
            'default_state' => 'kit',
        ]);
    }

    public function test_can_update_variation_profile(): void
    {
        $packagingA = $this->createPackaging('PKG-A');
        $packagingB = $this->createPackaging('PKG-B');

        $profile = FulfillmentVariationProfileModel::create([
            'item_id' => 333,
            'variation_id' => null,
            'variation_name' => 'Alt',
            'default_state' => 'kit',
            'default_packaging_id' => $packagingA->getKey(),
            'default_weight_kg' => 7.1,
            'assembly_option_id' => null,
        ]);

        $response = $this->put(route('fulfillment.masterdata.variations.update', $profile->getKey()), [
            'variation_name' => 'Neu',
            'default_state' => 'assembled',
            'default_packaging_id' => $packagingB->getKey(),
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.variations.edit', $profile->getKey()));
        $this->assertDatabaseHas('fulfillment_variation_profiles', [
            'id' => $profile->getKey(),
            'variation_name' => 'Neu',
            'default_state' => 'assembled',
            'default_packaging_id' => $packagingB->getKey(),
        ]);
    }

    public function test_can_delete_variation_profile(): void
    {
        $packaging = $this->createPackaging('PKG-DEL');
        $profile = FulfillmentVariationProfileModel::create([
            'item_id' => 555,
            'variation_id' => 666,
            'variation_name' => 'Löschbar',
            'default_state' => 'assembled',
            'default_packaging_id' => $packaging->getKey(),
            'default_weight_kg' => null,
            'assembly_option_id' => null,
        ]);

        $response = $this->delete(route('fulfillment.masterdata.variations.destroy', $profile->getKey()));

        $response->assertRedirect(route('fulfillment.masterdata.variations.index'));
        $this->assertDatabaseMissing('fulfillment_variation_profiles', [
            'id' => $profile->getKey(),
        ]);
    }
}
