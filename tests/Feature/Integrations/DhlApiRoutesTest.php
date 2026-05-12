<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderShipmentModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentPackageModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for all DHL API routes under /api/admin/dhl/*.
 *
 * Engineering-Handbuch §22 (API Regel): API-Endpunkte sind Verträge —
 * konsistent, versionierbar, verständlich.
 * §20 (Auth): Authentifizierung und Autorisierung immer auf dem Server prüfen.
 * §19 (Security): Externe Input gilt als unsicher, RLS als zweites Sicherheitsnetz.
 *
 * Routes:
 *   GET    /api/admin/dhl/timetable           — can:fulfillment.orders.view
 *   GET    /api/admin/dhl/products           — can:fulfillment.orders.view
 *   GET    /api/admin/dhl/services           — can:fulfillment.orders.view
 *   POST   /api/admin/dhl/validate-services   — can:fulfillment.orders.view
 *   GET    /api/admin/dhl/price-quote        — can:fulfillment.orders.view
 *   POST   /api/admin/dhl/booking            — can:fulfillment.orders.manage
 *   GET    /api/admin/dhl/booking/{id}       — can:fulfillment.orders.view
 *   GET    /api/admin/dhl/label/{id}         — can:fulfillment.orders.view
 *   DELETE /api/admin/dhl/shipment/{id}      — can:fulfillment.orders.manage
 *   POST   /api/admin/dhl/bulk-book          — can:fulfillment.orders.manage
 *   POST   /api/admin/dhl/bulk-cancel        — can:fulfillment.orders.manage
 *   GET    /api/admin/dhl/tracking/{tn}/events — can:fulfillment.orders.view
 */
final class DhlApiRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = '/api/admin/dhl';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDhlConfig();
        $this->seedDhlSystemSettings();
        $this->mockDhlAuthGateway();
    }

    /**
     * Seed required DHL system_settings rows so EloquentDhlConfigurationRepository::load()
     * does not throw DhlConfigurationException for missing 'dhl_*' keys.
     *
     * Engineering-Handbuch §15: technische Eingaben am Rand vorbefüllen,
     * damit die Domain-Invariante (Pflicht-Settings) im Test erfüllt ist.
     */
    private function seedDhlSystemSettings(): void
    {
        /** @var \App\Application\Configuration\SystemSettingService $settings */
        $settings = $this->app->make(\App\Application\Configuration\SystemSettingService::class);

        $required = [
            'dhl_auth_base_url' => ['https://test-auth.example', 'string'],
            'dhl_auth_username' => ['test', 'string'],
            'dhl_auth_password' => ['test', 'secret'],
            'dhl_freight_base_url' => ['https://freight.example', 'string'],
            'dhl_freight_api_key' => ['test-key', 'string'],
            'dhl_freight_api_secret' => ['test-secret', 'secret'],
            'dhl_default_account_number' => ['12345', 'string'],
            'dhl_freight_timeout' => ['30', 'int'],
            'dhl_freight_verify_ssl' => ['1', 'bool'],
        ];

        foreach ($required as $key => [$value, $type]) {
            $settings->set($key, $value, $type);
        }
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    /**
     * Configure DHL service URLs and credentials used by the controllers.
     */
    private function configureDhlConfig(): void
    {
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
                    'timetable' => '/info/time-table/v1/gettimetable',
                    'products' => '/info/products/services/v1/products',
                    'additional_services' => '/info/products/services/v1/products/{productId}/additionalservices',
                    'additional_services_validation' => '/info/products/services/v1/products/{productId}/additionalservices/validationresults',
                    'shipments' => '/shipping/orders/v1/sendtransportinstruction',
                    'price_quote' => '/info/pricequote/v1/quoteforprice',
                    'label' => '/shipping/labels/v1/printdocumentsbyid',
                    'print_documents' => '/shipping/labels/v1/printdocuments',
                    'print_multiple_documents' => '/shipping/labels/v1/printmultipledocuments',
                ],
                'timeout' => 5,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'circuit_breaker' => ['failures' => 3, 'cooldown' => 60],
                'log_channel' => 'null',
                'ping' => ['method' => 'GET', 'path' => '/health'],
                'verify' => true,
            ],
        ]);
    }

    /**
     * Mock the DHL Authentication Gateway to return a valid token.
     */
    private function mockDhlAuthGateway(): void
    {
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

    /**
     * Sign in as an admin user with all DHL permissions.
     */
    private function signInAsAdmin(): UserModel
    {
        return $this->signInWithRole('leiter');
    }

    /**
     * Sign in as a viewer (has admin access AND fulfillment.orders.view, but NOT fulfillment.orders.manage).
     * Use this for `.manage`-route 403 expectations.
     */
    private function signInAsViewer(): UserModel
    {
        return $this->signInWithRole('viewer');
    }

    /**
     * Sign in as a user with admin.access but NO fulfillment.orders.* permissions.
     * Use this for `.view`-route 403 expectations.
     */
    private function signInAsNoOrderAccess(): UserModel
    {
        return $this->signInWithRole('support');
    }

    /**
     * Attach a shipment with the given tracking number to a shipment order via the
     * shipment_order_shipments pivot. Domain ShipmentOrder hydrates trackingNumbers
     * from this relation — there is no `tracking_numbers` column on shipment_orders.
     */
    private function attachTrackingNumber(ShipmentOrderModel $order, string $trackingNumber): void
    {
        $shipment = ShipmentModel::factory()->create(['tracking_number' => $trackingNumber]);
        ShipmentOrderShipmentModel::factory()->create([
            'shipment_order_id' => $order->id,
            'shipment_id' => $shipment->id,
        ]);
    }

    /**
     * Create a persisted ShipmentOrder for routes that require an existing order.
     */
    private function makeShipmentOrder(): ShipmentOrderModel
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();

        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'is_booked' => false,
            'receiver_street' => 'Teststrasse 1',
            'receiver_postal_code' => '80331',
            'receiver_city_name' => 'Munich',
            'receiver_country_code' => 'DE',
            'receiver_company_name' => 'Acme GmbH',
            'receiver_contact_name' => 'Max Mustermann',
            'receiver_email' => 'max@example.com',
            'receiver_phone' => '+49 89 12345678',
        ]);

        $this->attachPackage($order);

        return $order;
    }

    /**
     * Attach at least one ShipmentPackage to an order so DhlPayloadAssembler
     * can assemble a valid 'pieces' array (DHL spec requires >= 1 piece).
     */
    private function attachPackage(ShipmentOrderModel $order, int $count = 1): void
    {
        ShipmentPackageModel::factory()->count($count)->create([
            'shipment_order_id' => $order->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Route 1: GET /api/admin/dhl/timetable
    // -------------------------------------------------------------------------

    public function test_timetable_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson(self::BASE_URL.'/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertUnauthorized();
    }

    public function test_timetable_returns_403_when_user_lacks_permission(): void
    {
        $this->signInAsNoOrderAccess();

        $response = $this->getJson(self::BASE_URL.'/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertForbidden();
    }

    public function test_timetable_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        Http::fake([
            'https://freight.example/info/time-table/v1/gettimetable' => Http::response([
                'slots' => [
                    ['departure' => '2026-05-12T08:00:00Z', 'arrival' => '2026-05-12T14:00:00Z'],
                ],
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => ['type', 'id', 'attributes'],
        ]);
        $response->assertJsonPath('data.type', 'dhl-timetable');
    }

    public function test_timetable_returns_422_when_required_params_missing(): void
    {
        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/timetable');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['origin_postal_code', 'destination_postal_code', 'pickup_date']);
    }

    // -------------------------------------------------------------------------
    // Route 2: GET /api/admin/dhl/products
    // -------------------------------------------------------------------------

    public function test_products_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson(self::BASE_URL.'/products');

        $response->assertUnauthorized();
    }

    public function test_products_returns_403_when_user_lacks_permission(): void
    {
        $this->signInAsNoOrderAccess();

        $response = $this->getJson(self::BASE_URL.'/products');

        $response->assertForbidden();
    }

    public function test_products_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        Http::fake([
            'https://freight.example/info/products/services/v1/products' => Http::response([
                [
                    'productId' => 'EXPRESS',
                    'name' => 'DHL Express',
                    'description' => 'International express shipping',
                    'validUntil' => '2026-12-31',
                ],
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/products');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['type', 'id', 'attributes' => ['product_id', 'name', 'description', 'valid_until']],
            ],
        ]);
        $response->assertJsonPath('data.0.type', 'dhl-product');
        $response->assertJsonPath('data.0.attributes.name', 'DHL Express');
    }

    // -------------------------------------------------------------------------
    // Route 3: GET /api/admin/dhl/services
    // -------------------------------------------------------------------------

    public function test_services_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson(self::BASE_URL.'/services?product_id=EXPRESS');

        $response->assertUnauthorized();
    }

    public function test_services_returns_403_when_user_lacks_permission(): void
    {
        $this->signInAsNoOrderAccess();

        $response = $this->getJson(self::BASE_URL.'/services?product_id=EXPRESS');

        $response->assertForbidden();
    }

    public function test_services_returns_422_when_product_id_missing(): void
    {
        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/services');

        $response->assertStatus(422);
        // Controller emits JSON-API error format (see InteractsWithJsonApiResponses)
        $response->assertJsonPath('errors.0.source.pointer', '/product_id');
    }

    public function test_services_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        Http::fake([
            'https://freight.example/info/products/services/v1/products/EXPRESS/additionalservices' => Http::response([
                [
                    'serviceCode' => 'S1',
                    'name' => 'Saturday Delivery',
                    'description' => 'Delivery on Saturday',
                ],
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/services?product_id=EXPRESS');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['type', 'id', 'attributes' => ['service_code', 'name', 'description']],
            ],
        ]);
        $response->assertJsonPath('data.0.type', 'dhl-service');
        $response->assertJsonPath('data.0.attributes.service_code', 'S1');
    }

    // -------------------------------------------------------------------------
    // Route 4: POST /api/admin/dhl/validate-services
    // -------------------------------------------------------------------------

    public function test_validate_services_returns_401_when_not_authenticated(): void
    {
        $response = $this->postJson(self::BASE_URL.'/validate-services', [
            'product_id' => 'EXPRESS',
            'services' => ['S1'],
        ]);

        $response->assertUnauthorized();
    }

    public function test_validate_services_returns_403_when_user_lacks_permission(): void
    {
        $this->signInAsNoOrderAccess();

        $response = $this->postJson(self::BASE_URL.'/validate-services', [
            'product_id' => 'EXPRESS',
            'services' => ['S1'],
        ]);

        $response->assertForbidden();
    }

    public function test_validate_services_returns_422_when_required_fields_missing(): void
    {
        $this->signInAsAdmin();

        $response = $this->postJson(self::BASE_URL.'/validate-services', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id', 'services']);
    }

    public function test_validate_services_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        Http::fake([
            'https://freight.example/*' => Http::response([
                'valid' => true,
                'errors' => [],
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->postJson(self::BASE_URL.'/validate-services', [
            'product_id' => 'EXPRESS',
            'services' => ['S1', 'S2'],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => ['valid', 'errors', 'product_id'],
            ],
        ]);
        $response->assertJsonPath('data.type', 'dhl-service-validation');
        $response->assertJsonPath('data.attributes.product_id', 'EXPRESS');
    }

    // -------------------------------------------------------------------------
    // Route 5: GET /api/admin/dhl/price-quote
    // -------------------------------------------------------------------------

    public function test_price_quote_returns_401_when_not_authenticated(): void
    {
        $order = $this->makeShipmentOrder();

        $response = $this->getJson(self::BASE_URL.'/price-quote?order_id='.$order->id);

        $response->assertUnauthorized();
    }

    public function test_price_quote_returns_403_when_user_lacks_permission(): void
    {
        $order = $this->makeShipmentOrder();
        $this->signInAsNoOrderAccess();

        $response = $this->getJson(self::BASE_URL.'/price-quote?order_id='.$order->id);

        $response->assertForbidden();
    }

    public function test_price_quote_returns_422_when_order_id_missing(): void
    {
        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/price-quote');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_id']);
    }

    public function test_price_quote_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        $order = $this->makeShipmentOrder();

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'totalPrice' => '15.50',
                'currency' => 'EUR',
                'breakdown' => [
                    ['item' => 'base', 'price' => '10.00'],
                    ['item' => 'fuel_surcharge', 'price' => '5.50'],
                ],
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/price-quote?'.http_build_query([
            'order_id' => $order->id,
            'product_id' => 'EUP',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => ['total_price', 'currency', 'breakdown'],
            ],
        ]);
        $response->assertJsonPath('data.type', 'dhl-price-quote');
        $response->assertJsonPath('data.attributes.currency', 'EUR');
    }

    // -------------------------------------------------------------------------
    // Route 6: POST /api/admin/dhl/booking
    // -------------------------------------------------------------------------

    public function test_booking_returns_401_when_not_authenticated(): void
    {
        $order = $this->makeShipmentOrder();

        $response = $this->postJson(self::BASE_URL.'/booking', [
            'order_id' => $order->id,
            'product_id' => 'EXPRESS',
        ]);

        $response->assertUnauthorized();
    }

    public function test_booking_returns_403_when_user_lacks_manage_permission(): void
    {
        $order = $this->makeShipmentOrder();
        $this->signInAsViewer();

        $response = $this->postJson(self::BASE_URL.'/booking', [
            'order_id' => $order->id,
            'product_id' => 'EXPRESS',
        ]);

        // viewer has admin.access but not fulfillment.orders.manage
        $response->assertForbidden();
    }

    public function test_booking_returns_422_when_order_id_missing(): void
    {
        $this->signInAsAdmin();

        // Send all OTHER required fields so only order_id is missing.
        // order_id uses 'sometimes' (web route uses path param) — but when
        // the API body omits it AND no route binding exists, the booking
        // service must fail validation. Here we expect 422 because product_code
        // alone is not enough; missing order_id must surface as a 422 with
        // an order_id validation error from the DhlBookingRequest enforcement.
        $response = $this->postJson(self::BASE_URL.'/booking', [
            'product_code' => 'EUP',
            'payer_code' => 'DAP',
            'default_package_type' => 'EUP',
            'product_id' => 'EXPRESS',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_id']);
    }

    public function test_booking_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        $order = $this->makeShipmentOrder();

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'shipmentId' => 'DHL-'.Str::upper(Str::random(8)),
                'trackingNumbers' => ['TRACK001', 'TRACK002'],
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->postJson(self::BASE_URL.'/booking', [
            'order_id' => $order->id,
            'product_code' => 'EUP',
            'payer_code' => 'DAP',
            'default_package_type' => 'EUP',
        ]);

        $response->assertCreated();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => ['shipment_id', 'tracking_numbers', 'booked_at', 'status'],
            ],
        ]);
        $response->assertJsonPath('data.type', 'dhl-booking');
        $response->assertJsonPath('data.attributes.status', 'booked');
    }

    // -------------------------------------------------------------------------
    // Route 7: GET /api/admin/dhl/booking/{shipmentOrderId}
    // -------------------------------------------------------------------------

    public function test_booking_show_returns_401_when_not_authenticated(): void
    {
        $order = $this->makeShipmentOrder();

        $response = $this->getJson(self::BASE_URL.'/booking/'.$order->id);

        $response->assertUnauthorized();
    }

    public function test_booking_show_returns_403_when_user_lacks_permission(): void
    {
        $order = $this->makeShipmentOrder();
        $this->signInAsNoOrderAccess();

        $response = $this->getJson(self::BASE_URL.'/booking/'.$order->id);

        $response->assertForbidden();
    }

    public function test_booking_show_returns_404_for_nonexistent_order(): void
    {
        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/booking/99999');

        $response->assertNotFound();
    }

    public function test_booking_show_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        $order = $this->makeShipmentOrder();

        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/booking/'.$order->id);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'shipment_id',
                    'tracking_numbers',
                    'booked_at',
                    'status',
                    'product_id',
                    'booking_error',
                    'label_url',
                    'pickup_reference',
                ],
            ],
        ]);
        $response->assertJsonPath('data.type', 'dhl-booking');
    }

    // -------------------------------------------------------------------------
    // Route 8: GET /api/admin/dhl/label/{shipmentOrderId}
    // -------------------------------------------------------------------------

    public function test_label_returns_401_when_not_authenticated(): void
    {
        $order = $this->makeShipmentOrder();

        $response = $this->getJson(self::BASE_URL.'/label/'.$order->id);

        $response->assertUnauthorized();
    }

    public function test_label_returns_403_when_user_lacks_permission(): void
    {
        $order = $this->makeShipmentOrder();
        // Route uses can:fulfillment.orders.view; viewer HAS that. Use a role
        // without fulfillment.orders.* permissions (support) to exercise 403.
        $this->signInAsNoOrderAccess();

        $response = $this->getJson(self::BASE_URL.'/label/'.$order->id);

        $response->assertForbidden();
    }

    public function test_label_returns_422_when_shipment_not_booked(): void
    {
        $order = $this->makeShipmentOrder();
        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/label/'.$order->id);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'Shipment has not been booked yet. Book the shipment first.');
    }

    public function test_label_returns_valid_json_api_response_when_authenticated_and_authorized_and_booked(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'is_booked' => true,
            'dhl_shipment_id' => 'DHL-12345',
        ]);
        $this->attachPackage($order);

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'labelUrl' => 'https://example.com/labels/12345.pdf',
                'labelPdfBase64' => base64_encode('FAKE PDF'),
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/label/'.$order->id);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => ['label_url', 'label_pdf_base64'],
            ],
        ]);
        $response->assertJsonPath('data.type', 'dhl-label');
    }

    // -------------------------------------------------------------------------
    // Route 9: DELETE /api/admin/dhl/shipment/{shipmentOrderId}
    // -------------------------------------------------------------------------

    public function test_cancel_returns_401_when_not_authenticated(): void
    {
        $order = $this->makeShipmentOrder();

        $response = $this->deleteJson(self::BASE_URL.'/shipment/'.$order->id);

        $response->assertUnauthorized();
    }

    public function test_cancel_returns_403_when_user_lacks_manage_permission(): void
    {
        $order = $this->makeShipmentOrder();
        $this->signInAsViewer();

        $response = $this->deleteJson(self::BASE_URL.'/shipment/'.$order->id);

        $response->assertForbidden();
    }

    public function test_cancel_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        // Cancellation requires a booked order with a dhl_shipment_id.
        // Otherwise DhlCancellationService returns success=false → 422.
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'is_booked' => true,
            'dhl_shipment_id' => 'DHL-CANCEL-TEST',
        ]);
        $this->attachPackage($order);

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'cancelledAt' => now()->toIso8601String(),
                'dhlConfirmationNumber' => 'CAN-'.Str::upper(Str::random(8)),
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->deleteJson(self::BASE_URL.'/shipment/'.$order->id, [
            'reason' => 'Test cancellation',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => ['shipment_order_id', 'cancelled_at', 'confirmation_number', 'status'],
            ],
        ]);
        $response->assertJsonPath('data.type', 'dhl-cancellation');
        $response->assertJsonPath('data.attributes.status', 'cancelled');
    }

    // -------------------------------------------------------------------------
    // Route 10: POST /api/admin/dhl/bulk-book
    // -------------------------------------------------------------------------

    public function test_bulk_booking_returns_401_when_not_authenticated(): void
    {
        $order1 = $this->makeShipmentOrder();
        $order2 = $this->makeShipmentOrder();

        $response = $this->postJson(self::BASE_URL.'/bulk-book', [
            'order_ids' => [$order1->id, $order2->id],
            'product_id' => 'EXPRESS',
        ]);

        $response->assertUnauthorized();
    }

    public function test_bulk_booking_returns_403_when_user_lacks_manage_permission(): void
    {
        $order1 = $this->makeShipmentOrder();
        $order2 = $this->makeShipmentOrder();
        $this->signInAsViewer();

        $response = $this->postJson(self::BASE_URL.'/bulk-book', [
            'order_ids' => [$order1->id, $order2->id],
            'product_id' => 'EXPRESS',
        ]);

        $response->assertForbidden();
    }

    public function test_bulk_booking_returns_422_when_order_ids_missing(): void
    {
        $this->signInAsAdmin();

        $response = $this->postJson(self::BASE_URL.'/bulk-book', [
            'product_id' => 'EXPRESS',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_ids']);
    }

    public function test_bulk_booking_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        $order1 = $this->makeShipmentOrder();
        $order2 = $this->makeShipmentOrder();

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'shipmentId' => 'DHL-'.Str::upper(Str::random(8)),
                'trackingNumbers' => ['TRACK001'],
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->postJson(self::BASE_URL.'/bulk-book', [
            'order_ids' => [$order1->id, $order2->id],
            'product_code' => 'EUP',
            'payer_code' => 'DAP',
            'default_package_type' => 'EUP',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => ['total', 'succeeded', 'failed', 'queued'],
            ],
        ]);
        $response->assertJsonPath('data.type', 'dhl-bulk-booking');
    }

    // -------------------------------------------------------------------------
    // Route 11: POST /api/admin/dhl/bulk-cancel
    // -------------------------------------------------------------------------

    public function test_bulk_cancel_returns_401_when_not_authenticated(): void
    {
        $order1 = $this->makeShipmentOrder();
        $order2 = $this->makeShipmentOrder();

        $response = $this->postJson(self::BASE_URL.'/bulk-cancel', [
            'order_ids' => [$order1->id, $order2->id],
        ]);

        $response->assertUnauthorized();
    }

    public function test_bulk_cancel_returns_403_when_user_lacks_manage_permission(): void
    {
        $order1 = $this->makeShipmentOrder();
        $order2 = $this->makeShipmentOrder();
        $this->signInAsViewer();

        $response = $this->postJson(self::BASE_URL.'/bulk-cancel', [
            'order_ids' => [$order1->id, $order2->id],
        ]);

        $response->assertForbidden();
    }

    public function test_bulk_cancel_returns_422_when_order_ids_missing(): void
    {
        $this->signInAsAdmin();

        $response = $this->postJson(self::BASE_URL.'/bulk-cancel', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_ids']);
    }

    public function test_bulk_cancel_returns_valid_json_api_response_when_authenticated_and_authorized(): void
    {
        $order1 = $this->makeShipmentOrder();
        $order2 = $this->makeShipmentOrder();

        Http::fake([
            'https://freight.example/*' => Http::response([
                'success' => true,
                'cancelledAt' => now()->toIso8601String(),
                'dhlConfirmationNumber' => 'CAN-'.Str::upper(Str::random(8)),
            ], 200),
        ]);

        $this->signInAsAdmin();

        $response = $this->postJson(self::BASE_URL.'/bulk-cancel', [
            'order_ids' => [$order1->id, $order2->id],
            'reason' => 'Bulk test cancellation',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => ['total', 'succeeded', 'failed'],
            ],
            'results',
        ]);
        $response->assertJsonPath('data.type', 'dhl-bulk-cancellation');
        $response->assertJsonPath('data.attributes.total', 2);
    }

    // -------------------------------------------------------------------------
    // Route 12: GET /api/admin/dhl/tracking/{trackingNumber}/events
    // -------------------------------------------------------------------------

    public function test_tracking_events_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson(self::BASE_URL.'/tracking/TRACK12345678/events');

        $response->assertUnauthorized();
    }

    public function test_tracking_events_returns_403_when_user_lacks_permission(): void
    {
        // Route uses can:fulfillment.orders.view; viewer HAS that. Use a role
        // without fulfillment.orders.* permissions (support) to exercise 403.
        $this->signInAsNoOrderAccess();

        $response = $this->getJson(self::BASE_URL.'/tracking/TRACK12345678/events');

        $response->assertForbidden();
    }

    public function test_tracking_events_returns_404_for_unknown_tracking_number(): void
    {
        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/tracking/UNKNOWN-TRACKING/events');

        $response->assertNotFound();
    }

    public function test_tracking_events_returns_valid_json_response_when_authenticated_and_authorized(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'is_booked' => true,
            'dhl_shipment_id' => 'DHL-12345',
        ]);
        $this->attachPackage($order);
        $this->attachTrackingNumber($order, 'TRACK12345678');

        // The GetShipmentDetail query fetches from DB, not from DHL gateway
        $this->signInAsAdmin();

        $response = $this->getJson(self::BASE_URL.'/tracking/TRACK12345678/events');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'success',
            'tracking_number',
            'current_status' => ['code', 'label'],
            'is_delivered',
            'events',
        ]);
        $response->assertJsonPath('tracking_number', 'TRACK12345678');
    }
}