<?php

namespace Tests\Feature\Fulfillment;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderItemModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentPackageModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('operations');
    }

    public function test_export_can_be_triggered_and_creates_file(): void
    {
        Storage::fake('local');

        $processedAt = CarbonImmutable::now()->subDay();

        $order = ShipmentOrderModel::create([
            'external_order_id' => 1001,
            'sender_code' => 'DHL',
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'total_amount' => 199.5,
            'processed_at' => $processedAt,
            'is_booked' => true,
            'booked_at' => $processedAt,
            'booked_by' => 'system',
        ]);

        ShipmentOrderItemModel::create([
            'shipment_order_id' => $order->id,
            'sku' => 'AX4-001',
            'description' => 'Test Item',
            'quantity' => 2,
            'is_assembly' => false,
        ]);

        ShipmentPackageModel::create([
            'shipment_order_id' => $order->id,
            'package_reference' => 'PKG-1',
            'quantity' => 1,
            'weight_kg' => 12.5,
            'length_mm' => 1200,
            'width_mm' => 800,
            'height_mm' => 600,
            'truck_slot_units' => 1,
        ]);

        $response = $this->post(route('csv-export.trigger'), [
            'processed_from' => $processedAt->format('Y-m-d'),
            'processed_to' => $processedAt->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $recent = session('recent_export');
        $this->assertIsArray($recent);
        $this->assertArrayHasKey('file_path', $recent);

        Storage::disk('local')->assertExists($recent['file_path']);

        $this->assertDatabaseCount('system_jobs', 1);
        $this->assertDatabaseHas('system_jobs', [
            'job_name' => 'fulfillment.csv_export',
            'status' => 'completed',
        ]);
    }

    public function test_generated_files_are_listed_on_page(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/demo.csv', 'content');

        $response = $this->get(route('csv-export'));

        $response->assertStatus(200);
        $response->assertSee('demo.csv');
    }
}
