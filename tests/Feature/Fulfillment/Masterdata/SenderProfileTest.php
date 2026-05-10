<?php

namespace Tests\Feature\Fulfillment\Masterdata;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SenderProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    private function senderPayload(array $overrides = []): array
    {
        return array_merge([
            'sender_code' => 'sender-'.uniqid(),
            'display_name' => 'Neutral Versand',
            'company_name' => 'Neutral GmbH',
            'contact_person' => 'Max Muster',
            'email' => 'neutral@example.com',
            'phone' => '+49 30 123456',
            'street_name' => 'Musterstraße',
            'street_number' => '1',
            'address_addition' => null,
            'postal_code' => '10115',
            'city' => 'Berlin',
            'country_iso2' => 'DE',
        ], $overrides);
    }

    public function test_index_displays_sender_profiles(): void
    {
        $model = FulfillmentSenderProfileModel::create($this->senderPayload([
            'sender_code' => 'display-test',
            'display_name' => 'Anzeige Test',
        ]));

        $response = $this->get(route('fulfillment.masterdata.senders.index'));

        $response->assertOk();
        $response->assertSee('Anzeige Test');
        $response->assertSee('display-test');
        $this->assertNotNull($model);
    }

    public function test_can_create_sender_profile(): void
    {
        $payload = $this->senderPayload([
            'sender_code' => 'create-test',
        ]);

        $response = $this->post(route('fulfillment.masterdata.senders.store'), $payload);

        $response->assertRedirect(route('fulfillment.masterdata.senders.index'));
        $this->assertDatabaseHas('fulfillment_sender_profiles', [
            'sender_code' => 'create-test',
            'display_name' => 'Neutral Versand',
        ]);
    }

    public function test_can_update_sender_profile(): void
    {
        $model = FulfillmentSenderProfileModel::create($this->senderPayload([
            'sender_code' => 'update-test',
        ]));

        $response = $this->put(route('fulfillment.masterdata.senders.update', $model->getKey()), [
            'display_name' => 'Aktualisiert',
            'city' => 'Hamburg',
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.senders.edit', $model->getKey()));
        $this->assertDatabaseHas('fulfillment_sender_profiles', [
            'id' => $model->getKey(),
            'display_name' => 'Aktualisiert',
            'city' => 'Hamburg',
        ]);
    }

    public function test_can_delete_sender_profile(): void
    {
        $model = FulfillmentSenderProfileModel::create($this->senderPayload([
            'sender_code' => 'delete-test',
        ]));

        $response = $this->delete(route('fulfillment.masterdata.senders.destroy', $model->getKey()));

        $response->assertRedirect(route('fulfillment.masterdata.senders.index'));
        $this->assertDatabaseMissing('fulfillment_sender_profiles', [
            'id' => $model->getKey(),
        ]);
    }
}
