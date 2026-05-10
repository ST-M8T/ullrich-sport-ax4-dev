<?php

namespace Tests\Feature\Fulfillment\Masterdata;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PackagingProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    public function test_index_displays_packaging_profiles(): void
    {
        $profile = FulfillmentPackagingProfileModel::create([
            'package_name' => 'Test Palette',
            'packaging_code' => 'TEST-01',
            'length_mm' => 1200,
            'width_mm' => 800,
            'height_mm' => 1000,
            'truck_slot_units' => 1,
            'max_units_per_pallet_same_recipient' => 10,
            'max_units_per_pallet_mixed_recipient' => 8,
            'max_stackable_pallets_same_recipient' => 2,
            'max_stackable_pallets_mixed_recipient' => 1,
            'notes' => null,
        ]);

        $response = $this->get(route('fulfillment.masterdata.packaging.index'));

        $response->assertOk();
        $response->assertSee('Test Palette');
        $response->assertSee((string) $profile->getKey());
    }

    public function test_can_create_packaging_profile(): void
    {
        $payload = [
            'package_name' => 'Neue Palette',
            'packaging_code' => 'NP-001',
            'length_mm' => 1000,
            'width_mm' => 800,
            'height_mm' => 500,
            'truck_slot_units' => 1,
            'max_units_per_pallet_same_recipient' => 12,
            'max_units_per_pallet_mixed_recipient' => 10,
            'max_stackable_pallets_same_recipient' => 3,
            'max_stackable_pallets_mixed_recipient' => 2,
            'notes' => 'Testnotiz',
        ];

        $response = $this->post(route('fulfillment.masterdata.packaging.store'), $payload);

        $response->assertRedirect(route('fulfillment.masterdata.packaging.index'));
        $this->assertDatabaseHas('fulfillment_packaging_profiles', [
            'package_name' => 'Neue Palette',
            'packaging_code' => 'NP-001',
        ]);
    }

    public function test_can_update_packaging_profile(): void
    {
        $profile = FulfillmentPackagingProfileModel::create([
            'package_name' => 'Alt',
            'packaging_code' => 'ALT-01',
            'length_mm' => 1000,
            'width_mm' => 600,
            'height_mm' => 400,
            'truck_slot_units' => 1,
            'max_units_per_pallet_same_recipient' => 8,
            'max_units_per_pallet_mixed_recipient' => 6,
            'max_stackable_pallets_same_recipient' => 2,
            'max_stackable_pallets_mixed_recipient' => 1,
            'notes' => null,
        ]);

        $response = $this->put(route('fulfillment.masterdata.packaging.update', $profile->getKey()), [
            'package_name' => 'Aktualisiert',
            'truck_slot_units' => 2,
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.packaging.edit', $profile->getKey()));
        $this->assertDatabaseHas('fulfillment_packaging_profiles', [
            'id' => $profile->getKey(),
            'package_name' => 'Aktualisiert',
            'truck_slot_units' => 2,
        ]);
    }

    public function test_can_delete_packaging_profile(): void
    {
        $profile = FulfillmentPackagingProfileModel::create([
            'package_name' => 'Löschprofil',
            'packaging_code' => 'DEL-01',
            'length_mm' => 900,
            'width_mm' => 600,
            'height_mm' => 500,
            'truck_slot_units' => 1,
            'max_units_per_pallet_same_recipient' => 5,
            'max_units_per_pallet_mixed_recipient' => 5,
            'max_stackable_pallets_same_recipient' => 2,
            'max_stackable_pallets_mixed_recipient' => 1,
            'notes' => null,
        ]);

        $response = $this->delete(route('fulfillment.masterdata.packaging.destroy', $profile->getKey()));

        $response->assertRedirect(route('fulfillment.masterdata.packaging.index'));
        $this->assertDatabaseMissing('fulfillment_packaging_profiles', [
            'id' => $profile->getKey(),
        ]);
    }
}
