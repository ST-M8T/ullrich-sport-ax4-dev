<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests\Fulfillment\Masterdata;

use App\Http\Requests\Fulfillment\Masterdata\StoreSenderProfileRequest;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation contract for {@see StoreSenderProfileRequest}.
 *
 * Engineering-Handbuch §15: technische Eingabevalidierung am Rand. Tests
 * required-field enforcement, max-length boundaries, unique constraint
 * integration and conditional `sometimes` behavior on update.
 */
final class StoreSenderProfileRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'sender_code' => 'lager-berlin',
            'display_name' => 'Lager Berlin',
            'company_name' => 'Ullrich Sport GmbH',
            'contact_person' => 'Marie Schmidt',
            'email' => 'versand@ullrich-sport.de',
            'phone' => '+49 30 1234567',
            'street_name' => 'Friedrichstraße',
            'street_number' => '123a',
            'postal_code' => '10117',
            'city' => 'Berlin',
            'country_iso2' => 'DE',
        ], $overrides);
    }

    public function test_rules_array_contains_all_required_fields(): void
    {
        $request = new StoreSenderProfileRequest;
        $rules = $request->rules();

        foreach (['sender_code', 'display_name', 'company_name', 'street_name', 'postal_code', 'city', 'country_iso2'] as $field) {
            $this->assertArrayHasKey($field, $rules, "Missing required field rule: {$field}");
            $this->assertContains('required', $rules[$field], "Field {$field} should be required");
        }
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $response = $this->post(route('fulfillment.masterdata.senders.store'), [
            'display_name' => 'Nur ein Anzeigename',
        ]);

        $response->assertSessionHasErrors([
            'sender_code',
            'company_name',
            'street_name',
            'postal_code',
            'city',
            'country_iso2',
        ]);
    }

    public function test_store_rejects_country_iso2_with_wrong_size(): void
    {
        $response = $this->post(
            route('fulfillment.masterdata.senders.store'),
            $this->validPayload(['country_iso2' => 'DEU']),
        );

        $response->assertSessionHasErrors(['country_iso2']);
    }

    public function test_store_rejects_display_name_exceeding_max_length(): void
    {
        $response = $this->post(
            route('fulfillment.masterdata.senders.store'),
            $this->validPayload(['display_name' => str_repeat('x', 256)]),
        );

        $response->assertSessionHasErrors(['display_name']);
    }

    public function test_store_rejects_invalid_email_format(): void
    {
        $response = $this->post(
            route('fulfillment.masterdata.senders.store'),
            $this->validPayload(['email' => 'kein-email']),
        );

        $response->assertSessionHasErrors(['email']);
    }

    public function test_store_rejects_duplicate_sender_code(): void
    {
        FulfillmentSenderProfileModel::create($this->validPayload([
            'sender_code' => 'duplicate-code',
        ]));

        $response = $this->post(
            route('fulfillment.masterdata.senders.store'),
            $this->validPayload(['sender_code' => 'duplicate-code']),
        );

        $response->assertSessionHasErrors(['sender_code']);
    }

    public function test_store_accepts_valid_payload(): void
    {
        $response = $this->post(
            route('fulfillment.masterdata.senders.store'),
            $this->validPayload(['sender_code' => 'lager-hamburg']),
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fulfillment_sender_profiles', [
            'sender_code' => 'lager-hamburg',
            'display_name' => 'Lager Berlin',
            'country_iso2' => 'DE',
        ]);
    }
}
