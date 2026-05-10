<?php

namespace Tests\Feature\Fulfillment\Masterdata;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentAssemblyOptionModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AssemblyOptionTest extends TestCase
{
    use RefreshDatabase;

    private function createPackaging(): FulfillmentPackagingProfileModel
    {
        return FulfillmentPackagingProfileModel::create([
            'package_name' => 'Basis Verpackung',
            'packaging_code' => 'PKG-01',
            'length_mm' => 1000,
            'width_mm' => 700,
            'height_mm' => 600,
            'truck_slot_units' => 1,
            'max_units_per_pallet_same_recipient' => 10,
            'max_units_per_pallet_mixed_recipient' => 8,
            'max_stackable_pallets_same_recipient' => 2,
            'max_stackable_pallets_mixed_recipient' => 1,
            'notes' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    public function test_index_displays_assembly_options(): void
    {
        $packaging = $this->createPackaging();

        FulfillmentAssemblyOptionModel::create([
            'assembly_item_id' => 1001,
            'assembly_packaging_id' => $packaging->getKey(),
            'assembly_weight_kg' => 12.5,
            'description' => 'Option A',
        ]);

        $response = $this->get(route('fulfillment.masterdata.assembly.index'));

        $response->assertOk();
        $response->assertSee('Option A');
        $response->assertSee('1001');
    }

    public function test_can_create_assembly_option(): void
    {
        $packaging = $this->createPackaging();

        $payload = [
            'assembly_item_id' => 2002,
            'assembly_packaging_id' => $packaging->getKey(),
            'assembly_weight_kg' => 8.2,
            'description' => 'Neue Option',
        ];

        $response = $this->post(route('fulfillment.masterdata.assembly.store'), $payload);

        $response->assertRedirect(route('fulfillment.masterdata.assembly.index'));
        $this->assertDatabaseHas('fulfillment_assembly_options', [
            'assembly_item_id' => 2002,
            'description' => 'Neue Option',
        ]);
    }

    public function test_can_update_assembly_option(): void
    {
        $packaging = $this->createPackaging();
        $option = FulfillmentAssemblyOptionModel::create([
            'assembly_item_id' => 3003,
            'assembly_packaging_id' => $packaging->getKey(),
            'assembly_weight_kg' => 7.0,
            'description' => 'Alt',
        ]);

        $response = $this->put(route('fulfillment.masterdata.assembly.update', $option->getKey()), [
            'description' => 'Aktualisiert',
            'assembly_weight_kg' => 9.3,
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.assembly.edit', $option->getKey()));
        $this->assertDatabaseHas('fulfillment_assembly_options', [
            'id' => $option->getKey(),
            'description' => 'Aktualisiert',
            'assembly_weight_kg' => 9.3,
        ]);
    }

    public function test_can_delete_assembly_option(): void
    {
        $packaging = $this->createPackaging();
        $option = FulfillmentAssemblyOptionModel::create([
            'assembly_item_id' => 4004,
            'assembly_packaging_id' => $packaging->getKey(),
        ]);

        $response = $this->delete(route('fulfillment.masterdata.assembly.destroy', $option->getKey()));

        $response->assertRedirect(route('fulfillment.masterdata.assembly.index'));
        $this->assertDatabaseMissing('fulfillment_assembly_options', [
            'id' => $option->getKey(),
        ]);
    }
}
