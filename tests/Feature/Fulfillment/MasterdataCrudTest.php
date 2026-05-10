<?php

namespace Tests\Feature\Fulfillment;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentAssemblyOptionModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MasterdataCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    public function test_user_can_create_packaging_profile(): void
    {
        $payload = [
            'package_name' => 'Eurobox XL',
            'packaging_code' => 'PKG-XL',
            'length_mm' => 800,
            'width_mm' => 600,
            'height_mm' => 400,
            'truck_slot_units' => 2,
            'max_units_per_pallet_same_recipient' => 10,
            'max_units_per_pallet_mixed_recipient' => 8,
            'max_stackable_pallets_same_recipient' => 3,
            'max_stackable_pallets_mixed_recipient' => 2,
            'notes' => 'Test profile',
        ];

        $response = $this->post(route('fulfillment.masterdata.packaging.store'), $payload);

        $response->assertRedirect(route('fulfillment.masterdata.packaging.index'));
        $this->assertDatabaseHas('fulfillment_packaging_profiles', [
            'package_name' => 'Eurobox XL',
            'packaging_code' => 'PKG-XL',
            'length_mm' => 800,
        ]);
    }

    public function test_user_can_create_assembly_option(): void
    {
        $packaging = FulfillmentPackagingProfileModel::factory()->create();

        $response = $this->post(route('fulfillment.masterdata.assembly.store'), [
            'assembly_item_id' => 9001,
            'assembly_packaging_id' => $packaging->getKey(),
            'assembly_weight_kg' => 12.5,
            'description' => 'Preassembled kit',
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.assembly.index'));
        $this->assertDatabaseHas('fulfillment_assembly_options', [
            'assembly_item_id' => 9001,
            'assembly_packaging_id' => $packaging->getKey(),
        ]);
    }

    public function test_user_can_create_variation_profile(): void
    {
        $packaging = FulfillmentPackagingProfileModel::factory()->create();
        $assembly = FulfillmentAssemblyOptionModel::factory()->create([
            'assembly_packaging_id' => $packaging->getKey(),
        ]);

        $response = $this->post(route('fulfillment.masterdata.variations.store'), [
            'item_id' => 500,
            'variation_id' => 501,
            'variation_name' => 'Sample Variation',
            'default_state' => 'assembled',
            'default_packaging_id' => $packaging->getKey(),
            'default_weight_kg' => 5.5,
            'assembly_option_id' => $assembly->getKey(),
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.variations.index'));
        $this->assertDatabaseHas('fulfillment_variation_profiles', [
            'item_id' => 500,
            'variation_id' => 501,
            'default_packaging_id' => $packaging->getKey(),
        ]);
    }

    public function test_user_can_create_sender_profile(): void
    {
        $response = $this->post(route('fulfillment.masterdata.senders.store'), [
            'sender_code' => 'SND-BE',
            'display_name' => 'Berlin Warehouse',
            'company_name' => 'Example GmbH',
            'contact_person' => 'Max Mustermann',
            'email' => 'logistics@example.test',
            'phone' => '+49 30 123456',
            'street_name' => 'Friedrichstraße',
            'street_number' => '12a',
            'address_addition' => '2. OG',
            'postal_code' => '10117',
            'city' => 'Berlin',
            'country_iso2' => 'DE',
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.senders.index'));
        $this->assertDatabaseHas('fulfillment_sender_profiles', [
            'sender_code' => 'snd-be',
            'city' => 'Berlin',
        ]);
    }

    public function test_user_can_create_sender_rule(): void
    {
        $sender = FulfillmentSenderProfileModel::factory()->create();

        $response = $this->post(route('fulfillment.masterdata.sender-rules.store'), [
            'priority' => 10,
            'rule_type' => 'shipping_country_equals',
            'match_value' => 'DE',
            'target_sender_id' => $sender->getKey(),
            'is_active' => true,
            'description' => 'Berlin zip rule',
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.sender-rules.index'));
        $this->assertDatabaseHas('fulfillment_sender_rules', [
            'rule_type' => 'shipping_country_equals',
            'match_value' => 'DE',
            'target_sender_id' => $sender->getKey(),
        ]);
    }

    public function test_user_can_create_freight_profile(): void
    {
        $response = $this->post(route('fulfillment.masterdata.freight.store'), [
            'shipping_profile_id' => 42,
            'label' => 'Express Freight',
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.freight.index'));
        $this->assertDatabaseHas('fulfillment_freight_profiles', [
            'shipping_profile_id' => 42,
            'label' => 'Express Freight',
        ]);
    }
}
