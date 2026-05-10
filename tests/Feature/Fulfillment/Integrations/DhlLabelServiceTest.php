<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment\Integrations;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlLabelService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DhlLabelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_label_success(): void
    {
        Http::fake([
            'api-sandbox.dhl.com/*' => Http::response([
                'labelUrl' => 'https://example.com/label.pdf',
                'pdfBase64' => base64_encode('PDF_CONTENT'),
                'format' => 'PDF',
            ], 200),
        ]);

        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => 'DHL-12345',
        ]);

        $service = $this->app->make(DhlLabelService::class);
        $result = $service->generateLabel(Identifier::fromInt($order->id));

        $this->assertTrue($result->success);
        $this->assertEquals('https://example.com/label.pdf', $result->labelUrl);
        $this->assertNotNull($result->labelPdfBase64);

        $order->refresh();
        $this->assertEquals('https://example.com/label.pdf', $order->dhl_label_url);
    }

    public function test_generate_label_no_shipment_id(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => null,
        ]);

        $service = $this->app->make(DhlLabelService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shipment order has no DHL shipment ID');

        $service->generateLabel(Identifier::fromInt($order->id));
    }

    public function test_download_label_as_pdf_from_base64(): void
    {
        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'dhl_shipment_id' => 'DHL-12345',
            'dhl_label_pdf_base64' => base64_encode('PDF_CONTENT'),
        ]);

        $service = $this->app->make(DhlLabelService::class);
        $pdf = $service->downloadLabelAsPdf(Identifier::fromInt($order->id));

        $this->assertNotNull($pdf);
        $this->assertEquals('PDF_CONTENT', base64_decode($pdf, true));
    }
}
