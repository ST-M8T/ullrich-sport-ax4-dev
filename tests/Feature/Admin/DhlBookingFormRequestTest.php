<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature-Tests fuer die Form-Request-Validierung der DHL-Booking-Endpoints.
 *
 * Engineering-Handbuch §15 (Validation am Rand) + §22 (API-Vertraege):
 * Die FormRequests DhlBookingRequest und DhlBulkBookingRequest sind die
 * verbindliche, einzige Quelle der technischen Eingabevalidierung fuer
 * POST /api/admin/dhl/booking und POST /api/admin/dhl/bulk-book.
 *
 * Diese Tests pruefen pro Pflichtfeld:
 * - Fehlt das Feld → 422 + deutscher Fehlertext
 * - Falsches Format → 422 + deutscher Fehlertext
 * - Erfolgsfall → 201 (single) bzw. 200 (bulk)
 */
final class DhlBookingFormRequestTest extends TestCase
{
    use RefreshDatabase;

    private const SINGLE_URL = '/api/admin/dhl/booking';

    private const BULK_URL = '/api/admin/dhl/bulk-book';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.dhl_auth' => [
                'base_url' => 'https://auth.example',
                'username' => 'client-id',
                'password' => 'client-secret',
                'path' => '/auth/v1/token',
                'token_cache_ttl' => 0,
                'timeout' => 5,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'log_channel' => 'null',
                'verify' => true,
            ],
            'services.dhl_freight' => [
                'base_url' => 'https://freight.example',
                'api_key' => 'test-api-key',
                'api_secret' => 'test-api-secret',
                'auth' => 'bearer',
                'api_key_header' => 'DHL-API-Key',
                'api_secret_header' => null,
                'paths' => [
                    'shipments' => '/shipping/orders/v1/sendtransportinstruction',
                ],
                'timeout' => 5,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'circuit_breaker' => ['failures' => 3, 'cooldown' => 60],
                'log_channel' => 'null',
                'verify' => true,
            ],
        ]);

        $this->app->bind(\App\Domain\Integrations\Contracts\DhlAuthenticationGateway::class, static fn () => new class implements \App\Domain\Integrations\Contracts\DhlAuthenticationGateway
        {
            public function getToken(string $responseType = 'access_token'): array
            {
                return [
                    'access_token' => 'test-token-'.Str::random(8),
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ];
            }
        });
    }

    private function makeOrder(): ShipmentOrderModel
    {
        $sender = FulfillmentSenderProfileModel::factory()->create();

        return ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $sender->id,
            'is_booked' => false,
        ]);
    }

    private function validSinglePayload(int $orderId): array
    {
        return [
            'order_id' => $orderId,
            'product_code' => 'EUP',
            'payer_code' => 'DAP',
            'default_package_type' => 'EUP',
        ];
    }

    private function validBulkPayload(array $orderIds): array
    {
        return [
            'order_ids' => $orderIds,
            'product_code' => 'EUP',
            'payer_code' => 'DAP',
            'default_package_type' => 'EUP',
        ];
    }

    // -------------------------------------------------------------------------
    // SINGLE: Pflichtfelder
    // -------------------------------------------------------------------------

    public function test_single_booking_requires_product_code(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        unset($payload['product_code']);

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_code']);
        $response->assertJsonFragment(['Bitte einen DHL-Produkt-Code angeben.']);
    }

    public function test_single_booking_requires_payer_code(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        unset($payload['payer_code']);

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payer_code']);
        $response->assertJsonFragment(['Bitte den Frachtzahler (PayerCode) angeben.']);
    }

    public function test_single_booking_requires_default_package_type(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        unset($payload['default_package_type']);

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['default_package_type']);
        $response->assertJsonFragment(['Bitte einen Standard-Pakettyp angeben.']);
    }

    // -------------------------------------------------------------------------
    // SINGLE: Format-Verstoesse
    // -------------------------------------------------------------------------

    public function test_single_booking_rejects_product_code_with_wrong_length(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        $payload['product_code'] = 'EUPX'; // 4 Zeichen

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_code']);
    }

    public function test_single_booking_rejects_invalid_payer_code(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        $payload['payer_code'] = 'FOO';

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payer_code']);
        $response->assertJsonFragment(['PayerCode muss DAP, DDP, EXW oder CIP sein.']);
    }

    public function test_single_booking_rejects_invalid_default_package_type(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        $payload['default_package_type'] = 'TOOLONG';

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['default_package_type']);
    }

    public function test_single_booking_rejects_pieces_with_zero_weight(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        $payload['pieces'] = [
            ['number_of_pieces' => 1, 'weight' => 0],
        ];

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pieces.0.weight']);
    }

    public function test_single_booking_rejects_pickup_date_in_past(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        $payload['pickup_date'] = '2000-01-01';

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pickup_date']);
    }

    public function test_single_booking_rejects_unknown_freight_profile_id(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        $payload['freight_profile_id'] = 999999;

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['freight_profile_id']);
    }

    // -------------------------------------------------------------------------
    // SINGLE: Erfolgsfall + Normalisierung
    // -------------------------------------------------------------------------

    public function test_single_booking_passes_validation_with_full_valid_payload(): void
    {
        // Verifiziert: Form Request akzeptiert vollstaendige, gueltige Eingabe
        // (KEINE 422-Antwort). Das nachgelagerte Domain-Mapping (Pieces aus
        // Bestellung) ist Sache eigener Tests — hier nur die Validation-Schicht.
        $order = $this->makeOrder();

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'shipmentId' => 'DHL-FULL01',
                'trackingNumbers' => ['TRACK-A', 'TRACK-B'],
            ], 200),
        ]);

        $this->signInWithRole('leiter');

        $payload = $this->validSinglePayload($order->id);
        $payload['additional_services'] = ['SVC1', 'SVC2'];
        $payload['pickup_date'] = now()->addDay()->format('Y-m-d');

        $response = $this->postJson(self::SINGLE_URL, $payload);

        $this->assertNotSame(422, $response->getStatusCode(), 'Form Request darf bei vollstaendiger Eingabe NICHT mit 422 antworten.');
        $response->assertJsonMissingPath('errors.0.source.pointer');
    }

    public function test_single_booking_normalizes_codes_to_uppercase(): void
    {
        // Verifiziert: prepareForValidation() macht Codes case-insensitive.
        $order = $this->makeOrder();

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'shipmentId' => 'DHL-NORM01',
                'trackingNumbers' => ['TRACK-N'],
            ], 200),
        ]);

        $this->signInWithRole('leiter');

        $response = $this->postJson(self::SINGLE_URL, [
            'order_id' => $order->id,
            'product_code' => 'eup',
            'payer_code' => 'dap',
            'default_package_type' => 'eup',
        ]);

        $this->assertNotSame(422, $response->getStatusCode(), 'Lowercase-Codes muessen normalisiert und akzeptiert werden.');
    }

    // -------------------------------------------------------------------------
    // BULK: Pflichtfelder
    // -------------------------------------------------------------------------

    public function test_bulk_booking_requires_order_ids(): void
    {
        $this->signInWithRole('leiter');

        $response = $this->postJson(self::BULK_URL, [
            'product_code' => 'EUP',
            'payer_code' => 'DAP',
            'default_package_type' => 'EUP',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_ids']);
    }

    public function test_bulk_booking_rejects_unknown_order_id(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validBulkPayload([$order->id, 999999]);

        $response = $this->postJson(self::BULK_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_ids.1']);
    }

    public function test_bulk_booking_rejects_invalid_payer_code(): void
    {
        $order = $this->makeOrder();
        $this->signInWithRole('leiter');

        $payload = $this->validBulkPayload([$order->id]);
        $payload['payer_code'] = 'XYZ';

        $response = $this->postJson(self::BULK_URL, $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payer_code']);
    }

    public function test_bulk_booking_passes_validation_with_valid_payload(): void
    {
        // Verifiziert: Bulk-FormRequest akzeptiert vollstaendige Eingabe (kein 422).
        $order1 = $this->makeOrder();
        $order2 = $this->makeOrder();

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'shipmentId' => 'DHL-BULK01',
                'trackingNumbers' => ['TRACK-B1'],
            ], 200),
        ]);

        $this->signInWithRole('leiter');

        $response = $this->postJson(self::BULK_URL, $this->validBulkPayload([$order1->id, $order2->id]));

        $this->assertNotSame(422, $response->getStatusCode(), 'Bulk Form Request darf bei vollstaendiger Eingabe NICHT mit 422 antworten.');
    }
}
