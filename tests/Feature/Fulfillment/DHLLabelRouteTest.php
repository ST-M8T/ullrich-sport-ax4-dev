<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DHLLabelRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_label_returns_200_with_valid_order_and_dhl_shipment(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => 'DHL-12345',
            'dhl_label_pdf_base64' => base64_encode('PDF_CONTENT'),
        ]);

        $this->signInWithRole('operations');

        $response = $this->get(
            route('fulfillment-orders.dhl.label.preview', ['order' => $order->id])
        );

        $response->assertOk();
        $response->assertViewIs('fulfillment.orders.dhl.label-preview');
        $response->assertViewHas('order');
        $response->assertViewHas('labelData');
        $response->assertViewHas('downloadUrl');
    }

    public function test_preview_label_returns_404_for_nonexistent_order(): void
    {
        $this->signInWithRole('operations');

        $response = $this->get(
            route('fulfillment-orders.dhl.label.preview', ['order' => 99999])
        );

        $response->assertNotFound();
    }

    public function test_preview_label_redirects_when_no_dhl_shipment_exists(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => null,
        ]);

        $this->signInWithRole('operations');

        $response = $this->get(
            route('fulfillment-orders.dhl.label.preview', ['order' => $order->id])
        );

        $response->assertRedirect(route('fulfillment-orders.show', ['order' => $order->id]));
        $response->assertSessionHasErrors('label');
    }

    public function test_preview_label_requires_authentication(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => 'DHL-12345',
        ]);

        $response = $this->get(
            route('fulfillment-orders.dhl.label.preview', ['order' => $order->id])
        );

        $response->assertRedirectToRoute('login');
    }

    public function test_download_label_returns_pdf_response_with_valid_order(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => 'DHL-67890',
            'dhl_label_pdf_base64' => base64_encode('PDF_CONTENT'),
        ]);

        $this->signInWithRole('operations');

        $response = $this->get(
            route('fulfillment-orders.dhl.label.download', ['order' => $order->id])
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('dhl-label-'.$order->id.'.pdf', $response->headers->get('Content-Disposition', ''));
        $this->assertEquals('PDF_CONTENT', $response->getContent());
    }

    public function test_download_label_returns_404_for_nonexistent_order(): void
    {
        $this->signInWithRole('operations');

        $response = $this->get(
            route('fulfillment-orders.dhl.label.download', ['order' => 99999])
        );

        $response->assertNotFound();
    }

    public function test_download_label_returns_error_when_no_label_exists(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => null,
            'dhl_label_pdf_base64' => null,
            'dhl_label_url' => null,
        ]);

        $this->signInWithRole('operations');

        $response = $this->get(
            route('fulfillment-orders.dhl.label.download', ['order' => $order->id])
        );

        $response->assertRedirect(route('fulfillment-orders.show', ['order' => $order->id]));
        // Error is flashed via withErrors, which survives the redirect
        $this->assertTrue(
            session()->has('errors') || $response->getSession()->has('errors'),
            'Session should have errors after redirect with label error'
        );
    }

    public function test_download_label_requires_authentication(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => 'DHL-67890',
            'dhl_label_pdf_base64' => base64_encode('PDF_CONTENT'),
        ]);

        $response = $this->get(
            route('fulfillment-orders.dhl.label.download', ['order' => $order->id])
        );

        $response->assertRedirectToRoute('login');
    }
}