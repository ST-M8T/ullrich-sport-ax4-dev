<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchMetricsModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchScanModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DispatchListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_list_index_returns_paginated_lists_without_scans_by_default(): void
    {
        config(['services.api.key' => 'test-key']);

        $list = DispatchListModel::query()->create([
            'reference' => 'LIST-001',
            'title' => 'Morning Load',
            'status' => 'open',
            'created_by_user_id' => null,
            'total_packages' => 3,
            'total_orders' => 2,
            'total_truck_slots' => 1,
            'notes' => 'Handle with care',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        DispatchMetricsModel::query()->create([
            'dispatch_list_id' => $list->getKey(),
            'total_orders' => 2,
            'total_packages' => 3,
            'total_items' => 6,
            'total_truck_slots' => 1,
            'metrics' => ['by_sender' => ['fireball' => 2]],
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        DispatchScanModel::query()->create([
            'dispatch_list_id' => $list->getKey(),
            'barcode' => 'TRACK-123',
            'shipment_order_id' => null,
            'captured_by_user_id' => null,
            'captured_at' => now()->subMinutes(30),
            'metadata' => ['lane' => 'A1'],
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $response = $this->getJson('/api/v1/dispatch-lists', [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'reference',
                    'title',
                    'status',
                    'total_orders',
                    'total_packages',
                    'total_truck_slots',
                    'created_by_user_id',
                    'totals' => [
                        'total_orders',
                        'total_packages',
                        'total_items',
                        'total_truck_slots',
                        'metrics',
                    ],
                    'scan_count',
                ],
            ],
            'meta' => ['page', 'per_page', 'total', 'total_pages'],
        ]);

        $response->assertJsonFragment([
            'reference' => 'LIST-001',
            'status' => 'open',
            'total_orders' => 2,
            'scan_count' => 1,
            'metrics' => ['by_sender' => ['fireball' => 2]],
        ]);

        $payload = $response->json('data.0');
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('scans', $payload);
    }

    public function test_dispatch_list_index_includes_scans_on_request(): void
    {
        config(['services.api.key' => 'test-key']);

        $list = DispatchListModel::query()->create([
            'reference' => 'LIST-002',
            'title' => 'Evening Load',
            'status' => 'open',
            'created_by_user_id' => null,
            'total_packages' => 1,
            'total_orders' => 1,
            'total_truck_slots' => 1,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        DispatchScanModel::query()->create([
            'dispatch_list_id' => $list->getKey(),
            'barcode' => 'TRACK-456',
            'captured_at' => now()->subMinutes(15),
            'metadata' => ['lane' => 'B2'],
            'created_at' => now()->subMinutes(15),
            'updated_at' => now()->subMinutes(15),
        ]);

        $response = $this->getJson('/api/v1/dispatch-lists?include=scans', [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'scan_count',
                    'scans' => [
                        [
                            'id',
                            'barcode',
                            'shipment_order_id',
                            'captured_by_user_id',
                            'captured_at',
                            'metadata',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertJsonFragment([
            'barcode' => 'TRACK-456',
        ]);
    }

    public function test_dispatch_list_store_scan_creates_scan(): void
    {
        config(['services.api.key' => 'test-key']);

        $list = DispatchListModel::query()->create([
            'status' => 'open',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $response = $this->postJson("/api/v1/dispatch-lists/{$list->getKey()}/scans", [
            'barcode' => '  TRACK-789  ',
            'captured_at' => '2024-02-01T10:00:00+00:00',
            'metadata' => ['lane' => 'C3'],
        ], [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertCreated()
            ->assertJsonPath('list_id', $list->getKey())
            ->assertJsonPath('scan.barcode', 'TRACK-789')
            ->assertJsonPath('scan_count', 1);

        $this->assertDatabaseHas('dispatch_scans', [
            'dispatch_list_id' => $list->getKey(),
            'barcode' => 'TRACK-789',
        ]);
    }

    public function test_dispatch_list_store_scan_rejects_closed_list(): void
    {
        config(['services.api.key' => 'test-key']);

        $closedAt = now()->subDay();
        $createdAt = now()->subDays(2);

        $list = DispatchListModel::query()->create([
            'status' => 'closed',
            'closed_by_user_id' => UserModel::factory()->create()->getKey(),
            'closed_at' => $closedAt,
            'created_at' => $createdAt,
            'updated_at' => $closedAt,
        ]);

        $list->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $closedAt,
        ])->save();

        $response = $this->postJson("/api/v1/dispatch-lists/{$list->getKey()}/scans", [
            'barcode' => 'TRACK-999',
        ], [
            'X-API-Key' => 'test-key',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Cannot add scans to a closed dispatch list.');

        $this->assertDatabaseMissing('dispatch_scans', [
            'dispatch_list_id' => $list->getKey(),
            'barcode' => 'TRACK-999',
        ]);
    }
}
