<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlBookingResult;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlShipmentBookingService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentPackageModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * E2E-Test fuer den DHL-Buchungsflow ueber den Web-Endpoint
 * POST /fulfillment/orders/{order}/dhl/book.
 *
 * Akzeptanzkriterium: "Auftrag #589 laesst sich nach Produkt-/Paketwahl per UI
 * erfolgreich buchen". Der Test simuliert exakt diesen Flow vom HTTP-Submit
 * bis zur ShipmentOrder-Mutation, mit gemockter DHL-Antwort am Service-Rand.
 *
 * Mocking-Grenze: DhlShipmentBookingService (Application Service) — siehe
 * Engineering-Handbuch §8 (Abhaengigkeitsregel) + §34 (externe Services
 * gekapselt). Das hier ist E2E aus Presentation-Sicht; die Verdrahtung
 * Domain ↔ Infrastructure wird in DhlShipmentBookingTest abgedeckt.
 */
final class DhlBookingEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function makeBookableOrder(): ShipmentOrderModel
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'is_booked' => false,
            'dhl_shipment_id' => null,
            'dhl_booking_error' => null,
        ]);
        ShipmentPackageModel::factory()->create([
            'shipment_order_id' => $order->id,
        ]);

        return $order;
    }

    public function test_post_dhl_book_succeeds_and_redirects_back_to_order(): void
    {
        $order = $this->makeBookableOrder();

        $this->mock(DhlShipmentBookingService::class, function (MockInterface $mock) use ($order): void {
            $mock->shouldReceive('bookShipment')
                ->once()
                ->withArgs(function (Identifier $id, DhlBookingOptions $opts) use ($order): bool {
                    return $id->toInt() === $order->id
                        && $opts->payerCode() !== null
                        && $opts->payerCode()->value === 'DAP';
                })
                ->andReturn(new DhlBookingResult(
                    true,
                    'DHL-589-OK',
                    ['TRACK-589-A', 'TRACK-589-B'],
                    null,
                ));
        });

        $this->signInWithRole('operations');

        $response = $this->post(
            route('fulfillment-orders.dhl.book', ['order' => $order->id]),
            [
                'product_code' => 'V53',
                'payer_code' => 'DAP',
                'default_package_type' => 'PLT',
            ]
        );

        $response->assertRedirect(route('fulfillment-orders.show', ['order' => $order->id]));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('errors');

        $this->assertStringContainsString(
            'DHL-589-OK',
            (string) session('success'),
            'Flash-Message muss die Shipment-ID transportieren.'
        );
        $this->assertStringContainsString(
            'TRACK-589-A',
            (string) session('success'),
            'Flash-Message muss die Tracking-Nummern transportieren.'
        );
    }

    public function test_post_dhl_book_with_dhl_validation_error_flashes_error(): void
    {
        $order = $this->makeBookableOrder();

        $this->mock(DhlShipmentBookingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('bookShipment')
                ->once()
                ->andReturn(new DhlBookingResult(
                    false,
                    null,
                    [],
                    'DHL-API 400: Invalid product ID for sender country.',
                ));
        });

        $this->signInWithRole('operations');

        $response = $this->post(
            route('fulfillment-orders.dhl.book', ['order' => $order->id]),
            [
                'product_code' => 'V53',
                'payer_code' => 'DAP',
                'default_package_type' => 'PLT',
            ]
        );

        $response->assertRedirect(route('fulfillment-orders.show', ['order' => $order->id]));
        $response->assertSessionHasErrors('dhl_booking');
        $response->assertSessionMissing('success');

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString(
            'Invalid product ID',
            (string) $errors->first('dhl_booking'),
        );
    }

    public function test_post_dhl_book_requires_authentication(): void
    {
        $order = $this->makeBookableOrder();

        $response = $this->post(
            route('fulfillment-orders.dhl.book', ['order' => $order->id]),
            [
                'product_code' => 'V53',
                'payer_code' => 'DAP',
                'default_package_type' => 'PLT',
            ]
        );

        $response->assertRedirectToRoute('login');
    }

    public function test_post_dhl_book_rejects_invalid_payer_code(): void
    {
        $order = $this->makeBookableOrder();

        $this->mock(DhlShipmentBookingService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('bookShipment');
        });

        $this->signInWithRole('operations');

        $response = $this->from(route('fulfillment-orders.show', ['order' => $order->id]))
            ->post(
                route('fulfillment-orders.dhl.book', ['order' => $order->id]),
                [
                    'product_code' => 'V53',
                    'payer_code' => 'XXX', // not in DAP/DDP/EXW/CIP
                    'default_package_type' => 'PLT',
                ]
            );

        $response->assertSessionHasErrors('payer_code');
    }
}
