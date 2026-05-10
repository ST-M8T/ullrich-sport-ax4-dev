<?php

namespace Tests\Feature\Fulfillment\Masterdata;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentFreightProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FreightProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    public function test_index_displays_freight_profiles(): void
    {
        FulfillmentFreightProfileModel::create([
            'shipping_profile_id' => 999,
            'label' => 'Express',
        ]);

        $response = $this->get(route('fulfillment.masterdata.freight.index'));

        $response->assertOk();
        $response->assertSee('Express');
        $response->assertSee('999');
    }

    public function test_can_create_freight_profile(): void
    {
        $payload = [
            'shipping_profile_id' => 1234,
            'label' => 'Standard Versand',
        ];

        $response = $this->post(route('fulfillment.masterdata.freight.store'), $payload);

        $response->assertRedirect(route('fulfillment.masterdata.freight.index'));
        $this->assertDatabaseHas('fulfillment_freight_profiles', [
            'shipping_profile_id' => 1234,
            'label' => 'Standard Versand',
        ]);
    }

    public function test_can_update_freight_profile(): void
    {
        FulfillmentFreightProfileModel::create([
            'shipping_profile_id' => 777,
            'label' => 'Alt Label',
        ]);

        $response = $this->put(route('fulfillment.masterdata.freight.update', 777), [
            'label' => 'Neu Label',
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.freight.edit', 777));
        $this->assertDatabaseHas('fulfillment_freight_profiles', [
            'shipping_profile_id' => 777,
            'label' => 'Neu Label',
        ]);
    }

    public function test_can_delete_freight_profile(): void
    {
        FulfillmentFreightProfileModel::create([
            'shipping_profile_id' => 888,
            'label' => 'Löschbar',
        ]);

        $response = $this->delete(route('fulfillment.masterdata.freight.destroy', 888));

        $response->assertRedirect(route('fulfillment.masterdata.freight.index'));
        $this->assertDatabaseMissing('fulfillment_freight_profiles', [
            'shipping_profile_id' => 888,
        ]);
    }
}
