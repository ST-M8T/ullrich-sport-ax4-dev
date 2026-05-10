<?php

namespace Tests\Feature\Fulfillment\Masterdata;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderRuleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SenderRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    private function createSender(array $overrides = []): FulfillmentSenderProfileModel
    {
        return FulfillmentSenderProfileModel::create(array_merge([
            'sender_code' => 'rule-sender',
            'display_name' => 'Rule Sender',
            'company_name' => 'Rule GmbH',
            'contact_person' => null,
            'email' => null,
            'phone' => null,
            'street_name' => 'Rule Straße',
            'street_number' => '10',
            'address_addition' => null,
            'postal_code' => '20095',
            'city' => 'Hamburg',
            'country_iso2' => 'DE',
        ], $overrides));
    }

    public function test_index_displays_sender_rules(): void
    {
        $sender = $this->createSender([
            'sender_code' => 'rule-index',
            'display_name' => 'Index Sender',
        ]);

        FulfillmentSenderRuleModel::create([
            'priority' => 10,
            'rule_type' => 'billing_email_contains',
            'match_value' => '@example.com',
            'target_sender_id' => $sender->getKey(),
            'is_active' => true,
            'description' => 'Testregel',
        ]);

        $response = $this->get(route('fulfillment.masterdata.sender-rules.index'));

        $response->assertOk();
        $response->assertSee('@example.com');
        $response->assertSee('Testregel');
    }

    public function test_can_create_sender_rule(): void
    {
        $sender = $this->createSender([
            'sender_code' => 'rule-create',
        ]);

        $payload = [
            'priority' => 20,
            'rule_type' => 'plenty_id_equals',
            'match_value' => '12345',
            'target_sender_id' => $sender->getKey(),
            'is_active' => true,
            'description' => 'Neue Regel',
        ];

        $response = $this->post(route('fulfillment.masterdata.sender-rules.store'), $payload);

        $response->assertRedirect(route('fulfillment.masterdata.sender-rules.index'));
        $this->assertDatabaseHas('fulfillment_sender_rules', [
            'match_value' => '12345',
            'target_sender_id' => $sender->getKey(),
        ]);
    }

    public function test_can_update_sender_rule(): void
    {
        $sender = $this->createSender([
            'sender_code' => 'rule-update',
        ]);

        $rule = FulfillmentSenderRuleModel::create([
            'priority' => 45,
            'rule_type' => 'customer_id_equals',
            'match_value' => 'AB-100',
            'target_sender_id' => $sender->getKey(),
            'is_active' => true,
            'description' => 'Alt',
        ]);

        $response = $this->put(route('fulfillment.masterdata.sender-rules.update', $rule->getKey()), [
            'priority' => 5,
            'is_active' => false,
            'description' => 'Neu',
        ]);

        $response->assertRedirect(route('fulfillment.masterdata.sender-rules.edit', $rule->getKey()));
        $this->assertDatabaseHas('fulfillment_sender_rules', [
            'id' => $rule->getKey(),
            'priority' => 5,
            'is_active' => 0,
            'description' => 'Neu',
        ]);
    }

    public function test_can_delete_sender_rule(): void
    {
        $sender = $this->createSender([
            'sender_code' => 'rule-delete',
        ]);

        $rule = FulfillmentSenderRuleModel::create([
            'priority' => 30,
            'rule_type' => 'shipping_country_equals',
            'match_value' => 'AT',
            'target_sender_id' => $sender->getKey(),
            'is_active' => true,
            'description' => null,
        ]);

        $response = $this->delete(route('fulfillment.masterdata.sender-rules.destroy', $rule->getKey()));

        $response->assertRedirect(route('fulfillment.masterdata.sender-rules.index'));
        $this->assertDatabaseMissing('fulfillment_sender_rules', [
            'id' => $rule->getKey(),
        ]);
    }
}
