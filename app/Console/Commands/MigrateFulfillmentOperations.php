<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MigrateFulfillmentOperations extends Command
{
    protected $signature = 'fulfillment:migrate-operations
        {--dry-run : Preview migration without writing to the new schema}
        {--chunk=500 : Number of source rows to process per chunk}';

    protected $description = 'Migrates operational data (users, orders, shipments, dispatch, tracking, logs) from the source schema into the new fulfillment schema.';

    private bool $dryRun = false;

    private int $chunkSize = 500;

    private ConnectionInterface $source;

    /** @var array<string,array<string,int>> */
    private array $summary = [
        'users' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'user_login_attempts' => ['processed' => 0, 'created' => 0],
        'orders' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'order_items' => ['processed' => 0, 'created' => 0],
        'shipments' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'shipment_events' => ['processed' => 0, 'created' => 0],
        'dispatch_lists' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'dispatch_scans' => ['processed' => 0, 'created' => 0],
        'dispatch_metrics' => ['processed' => 0, 'created' => 0],
        'audit_logs' => ['processed' => 0, 'created' => 0],
        'system_settings' => ['processed' => 0, 'created' => 0, 'updated' => 0],
        'tracking_events_log' => ['processed' => 0, 'created' => 0],
    ];

    /** @var array<int,int> */
    private array $orderIdMap = [];

    /** @var array<int,int> */
    private array $externalOrderMap = [];

    /** @var array<int,int> */
    private array $shipmentIdMap = [];

    /** @var array<string,int> */
    private array $senderMap = [];

    /** @var array<string,int> */
    private array $packagingMap = [];

    /** @var array<string,int> */
    private array $userMap = [];

    /** @var string[] */
    private array $warnings = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->chunkSize = (int) max(100, (int) $this->option('chunk'));

        $this->source = DB::connection('ax4_source');

        $this->info('Fulfillment operational migration');
        $this->info($this->dryRun ? 'Mode: dry-run (no changes will be written).' : 'Mode: live write (changes persist).');

        $this->bootstrapReferenceMaps();

        if (! $this->dryRun) {
            DB::transaction(function () {
                $this->runMigration();
            });
        } else {
            $this->runMigration();
        }

        $this->renderSummary();
        $this->renderWarnings();

        return self::SUCCESS;
    }

    private function runMigration(): void
    {
        $this->migrateUsers();
        $this->migrateUserLoginAttempts();

        // Refresh user map in case new users inserted
        $this->refreshUserMap();

        $this->migrateOrders();
        $this->migrateOrderItems();

        $this->refreshOrderMap();

        $this->migrateShipments();
        $this->refreshShipmentMap();
        $this->migrateShipmentEvents();

        $this->migrateDispatchLists();
        $this->migrateDispatchScans();
        $this->migrateDispatchMetrics();

        $this->migrateAuditLogs();
        $this->migrateSystemSettings();
        $this->migrateTrackingEventsLog();
    }

    private function bootstrapReferenceMaps(): void
    {
        $this->senderMap = DB::table('fulfillment_sender_profiles')->pluck('id', 'sender_code')->all();
        $this->packagingMap = DB::table('fulfillment_packaging_profiles')->pluck('id', 'packaging_code')->all();
        $this->refreshUserMap();
        $this->refreshOrderMap();
        $this->refreshShipmentMap();
    }

    private function refreshUserMap(): void
    {
        $this->userMap = DB::table('users')->pluck('id', 'username')->all();
    }

    private function refreshOrderMap(): void
    {
        $this->externalOrderMap = DB::table('shipment_orders')->pluck('id', 'external_order_id')->all();
        $this->orderIdMap = $this->externalOrderMap; // Alias für Kompatibilität
    }

    private function refreshShipmentMap(): void
    {
        $this->shipmentIdMap = DB::table('shipments')->pluck('id', 'tracking_number')->all();
    }

    private function migrateUsers(): void
    {
        if (! $this->sourceTableExists('users')) {
            $this->warn('Source table `users` not found – skipping users migration.');

            return;
        }

        $sourceUsers = $this->source->table('users')->orderBy('id')->get();

        foreach ($sourceUsers as $user) {
            $this->summary['users']['processed']++;

            $payload = [
                'id' => (int) $user->id,
                'username' => strtolower(trim((string) $user->username)),
                'display_name' => $user->name ?? null,
                'email' => isset($user->email) && $user->email ? strtolower(trim((string) $user->email)) : null,
                'password_hash' => $user->password_hash ?? $user->password ?? '',
                'role' => isset($user->role) && $user->role ? strtolower(trim((string) $user->role)) : 'user',
                'must_change_password' => (bool) ($user->must_change_password ?? true),
                'disabled' => (bool) ($user->disabled ?? false),
                'last_login_at' => $this->normalizeTimestamp($user->last_login_at ?? null),
                'created_at' => $this->normalizeTimestamp($user->created_at ?? now()),
                'updated_at' => $this->normalizeTimestamp($user->updated_at ?? now()),
            ];

            if ($this->dryRun) {
                continue;
            }

            $existing = DB::table('users')->where('id', $payload['id'])->first();
            if ($existing) {
                DB::table('users')->where('id', $payload['id'])->update(Arr::except($payload, ['id', 'created_at']));
                $this->summary['users']['updated']++;
            } else {
                DB::table('users')->insert($payload);
                $this->summary['users']['created']++;
            }
        }
    }

    private function migrateUserLoginAttempts(): void
    {
        if (! $this->sourceTableExists('user_login_attempts')) {
            return;
        }

        $this->source->table('user_login_attempts')
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows) {
                foreach ($rows as $row) {
                    $this->summary['user_login_attempts']['processed']++;

                    if ($this->dryRun) {
                        continue;
                    }

                    $payload = [
                        'id' => (int) $row->id,
                        'username' => $row->username,
                        'ip_address' => $row->ip_address,
                        'user_agent' => $row->user_agent,
                        'success' => (bool) $row->success,
                        'failure_reason' => $row->failure_reason,
                        'created_at' => $this->normalizeTimestamp($row->created_at ?? now()),
                    ];

                    $exists = DB::table('user_login_attempts')->where('id', $payload['id'])->exists();
                    if (! $exists) {
                        DB::table('user_login_attempts')->insert($payload);
                        $this->summary['user_login_attempts']['created']++;
                    }
                }
            });
    }

    private function migrateOrders(): void
    {
        if (! $this->sourceTableExists('processed_orders')) {
            $this->warn('Source table `processed_orders` not found – skipping order migration.');

            return;
        }

        $senderMap = $this->senderMap;

        $this->source->table('processed_orders')
            ->orderBy('order_id')
            ->chunk($this->chunkSize, function (Collection $rows) use ($senderMap) {
                foreach ($rows as $row) {
                    $sourceOrderId = (int) $row->order_id;
                    $this->summary['orders']['processed']++;

                    $senderId = null;
                    $neutral = (string) ($row->neutral_code ?? '');
                    if ($neutral !== '' && isset($senderMap[$neutral])) {
                        $senderId = $senderMap[$neutral];
                    }

                    $payload = [
                        'external_order_id' => $sourceOrderId,
                        'customer_number' => $this->intOrNull($row->kunden_id ?? null),
                        'plenty_order_id' => $this->intOrNull($row->plenty_id ?? null),
                        'order_type' => $row->order_type ?? null,
                        'sender_profile_id' => $senderId,
                        'sender_code' => $neutral ?: null,
                        'contact_email' => $row->email ?? null,
                        'contact_phone' => $row->phone ?? null,
                        'destination_country' => $row->country ?? null,
                        'currency' => 'EUR',
                        'total_amount' => $this->decimalOrNull($row->rechbetrag ?? null),
                        'processed_at' => $this->normalizeTimestamp($row->processed_at ?? null),
                        'is_booked' => (bool) ($row->is_booked ?? false),
                        'booked_at' => $this->normalizeTimestamp($row->booked_at ?? null),
                        'booked_by' => $row->booked_by ?? null,
                        'shipped_at' => $this->normalizeTimestamp($row->shipped_at ?? null),
                        'last_export_filename' => $row->csv_file ?? null,
                        'metadata' => $this->encodeJson([
                            'neutral_code' => $neutral ?: null,
                            'tracking_number' => $row->tracking_number ?? null,
                        ]),
                        'updated_at' => now(),
                    ];

                    if ($this->dryRun) {
                        continue;
                    }

                    $existing = DB::table('shipment_orders')
                        ->where('external_order_id', $sourceOrderId)
                        ->first();

                    if ($existing) {
                        DB::table('shipment_orders')
                            ->where('id', $existing->id)
                            ->update(Arr::except($payload, ['external_order_id', 'metadata']) + [
                                'metadata' => $this->mergeMetadata($existing->metadata ?? null, $payload['metadata']),
                            ]);
                        $this->summary['orders']['updated']++;
                    } else {
                        $orderId = DB::table('shipment_orders')->insertGetId($payload + [
                            'created_at' => now(),
                        ]);
                        $this->summary['orders']['created']++;
                        $this->orderIdMap[$sourceOrderId] = $orderId;
                    }
                }
            });
    }

    private function migrateOrderItems(): void
    {
        if (! $this->sourceTableExists('processed_order_items')) {
            return;
        }

        $packagingMap = $this->packagingMap;

        $this->source->table('processed_order_items as poi')
            ->leftJoin('tische as t', 'poi.item_id', '=', 't.item_id')
            ->select([
                'poi.id',
                'poi.order_id',
                'poi.item_id',
                'poi.qty',
                'poi.typ',
                'poi.is_vormontiert',
                't.name as table_name',
            ])
            ->orderBy('poi.order_id')
            ->chunk($this->chunkSize, function (Collection $rows) use ($packagingMap) {
                foreach ($rows as $row) {
                    $this->summary['order_items']['processed']++;

                    $orderId = (int) $row->order_id;
                    $mappedOrderId = $this->orderIdMap[$orderId] ?? null;

                    if (! $mappedOrderId && ! $this->dryRun) {
                        $existing = DB::table('shipment_orders')
                            ->where('external_order_id', $orderId)
                            ->value('id');
                        if ($existing) {
                            $this->orderIdMap[$orderId] = (int) $existing;
                            $mappedOrderId = (int) $existing;
                        }
                    }

                    if (! $mappedOrderId) {
                        $this->warnings[] = "Skipping order item {$row->id}: Order {$orderId} not migrated.";

                        continue;
                    }

                    $sourceTyp = (string) ($row->typ ?? '');
                    $packagingId = $sourceTyp !== '' ? ($packagingMap[$sourceTyp] ?? null) : null;

                    $payload = [
                        'id' => (int) $row->id,
                        'shipment_order_id' => $mappedOrderId,
                        'item_id' => $this->intOrNull($row->item_id),
                        'variation_id' => null,
                        'sku' => null,
                        'description' => $row->table_name ?: $sourceTyp ?: ('Item #'.$row->item_id),
                        'quantity' => (int) $row->qty,
                        'packaging_profile_id' => $packagingId,
                        'weight_kg' => null,
                        'is_assembly' => (bool) ($row->is_vormontiert ?? false),
                        'metadata' => $this->encodeJson([
                            'packaging_code' => $sourceTyp ?: null,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($this->dryRun) {
                        continue;
                    }

                    $exists = DB::table('shipment_order_items')->where('id', $payload['id'])->exists();
                    if (! $exists) {
                        DB::table('shipment_order_items')->insert($payload);
                        $this->summary['order_items']['created']++;
                    }
                }
            });
    }

    private function migrateShipments(): void
    {
        if (! $this->sourceTableExists('shipments')) {
            return;
        }

        $this->source->table('shipments')
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows) {
                foreach ($rows as $row) {
                    $this->summary['shipments']['processed']++;

                    $trackingNumber = (string) $row->tracking_number;
                    if ($trackingNumber === '') {
                        $this->warnings[] = "Shipment {$row->id} skipped due to empty tracking number.";

                        continue;
                    }

                    $payload = [
                        'carrier_code' => $row->carrier ?? 'unknown',
                        'shipping_profile_id' => $this->intOrNull($row->shipping_profile_id ?? null),
                        'tracking_number' => $trackingNumber,
                        'status_code' => $row->status_code ?? null,
                        'status_description' => $row->status_text ?? null,
                        'last_event_at' => $this->normalizeTimestamp($row->last_event_at ?? null),
                        'delivered_at' => $this->normalizeTimestamp($row->delivered_at ?? null),
                        'next_sync_after' => $this->normalizeTimestamp($row->next_sync_after ?? null),
                        'weight_kg' => $this->normalizeWeight($row->weight ?? null, $row->weight_unit ?? null),
                        'volume_dm3' => $this->normalizeVolume($row->volume ?? null, $row->volume_unit ?? null),
                        'pieces_count' => $this->intOrNull($row->total_pieces ?? null),
                        'failed_attempts' => (int) ($row->failed_attempts ?? 0),
                        'last_payload' => $this->decodeJson($row->raw_last_payload ?? null),
                        'metadata' => $this->encodeJson([
                            'reference' => $row->reference ?? null,
                            'avisierung_status' => $row->avisierung_status ?? null,
                            'current_city' => $row->current_city ?? null,
                            'current_country' => $row->current_country ?? null,
                            'origin_city' => $row->origin_city ?? null,
                            'origin_country' => $row->origin_country ?? null,
                            'destination_city' => $row->destination_city ?? null,
                            'destination_country' => $row->destination_country ?? null,
                            'promised_delivery_date' => $row->promised_delivery_date ?? null,
                            'estimated_delivery_date' => $row->estimated_delivery_date ?? null,
                            'days_in_transit' => $row->days_in_transit ?? null,
                            'last_location_change' => $row->last_location_change ?? null,
                            'product_name' => $row->product_name ?? null,
                            'service_type' => $row->service_type ?? null,
                            'shipment_id' => $row->shipment_id ?? null,
                            'piece_ids' => $row->piece_ids ?? null,
                            'proof_of_delivery_available' => $row->proof_of_delivery_available ?? null,
                            'return_flag' => $row->return_flag ?? null,
                            'stuck_in_koblenz' => $row->stuck_in_koblenz ?? null,
                            'stuck_since' => $row->stuck_since ?? null,
                            'escalation_level' => $row->escalation_level ?? null,
                            'escalation_notes' => $row->escalation_notes ?? null,
                            'archived' => $row->archived ?? null,
                            'archived_at' => $row->archived_at ?? null,
                            'last_http_status' => $row->last_http_status ?? null,
                            'appointment_time' => $row->appointment_time ?? null,
                        ]),
                        'updated_at' => now(),
                    ];

                    if ($this->dryRun) {
                        continue;
                    }

                    $existing = DB::table('shipments')
                        ->where('tracking_number', $trackingNumber)
                        ->first();

                    if ($existing) {
                        DB::table('shipments')
                            ->where('id', $existing->id)
                            ->update(Arr::except($payload, ['tracking_number', 'last_payload', 'metadata']) + [
                                'last_payload' => $payload['last_payload'],
                                'metadata' => $this->mergeMetadata($existing->metadata ?? null, $payload['metadata']),
                            ]);
                        $this->summary['shipments']['updated']++;
                        $this->shipmentIdMap[$trackingNumber] = (int) $existing->id;
                        $shipmentId = (int) $existing->id;
                    } else {
                        $shipmentId = DB::table('shipments')->insertGetId($payload + [
                            'created_at' => now(),
                        ]);
                        $this->summary['shipments']['created']++;
                        $this->shipmentIdMap[$trackingNumber] = $shipmentId;
                    }

                    $sourceOrderId = $this->intOrNull($row->order_id ?? null);
                    if ($sourceOrderId) {
                        $orderId = $this->orderIdMap[$sourceOrderId] ?? null;
                        if (! $orderId) {
                            $orderId = DB::table('shipment_orders')
                                ->where('external_order_id', $sourceOrderId)
                                ->value('id');
                            if ($orderId) {
                                $this->orderIdMap[$sourceOrderId] = (int) $orderId;
                            }
                        }
                        if ($orderId && ! $this->dryRun) {
                            DB::table('shipment_order_shipments')->updateOrInsert(
                                [
                                    'shipment_order_id' => $orderId,
                                    'shipment_id' => $shipmentId,
                                ],
                                [
                                    'updated_at' => now(),
                                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                                ]
                            );
                        }
                    }
                }
            });
    }

    private function migrateShipmentEvents(): void
    {
        if (! $this->sourceTableExists('shipment_events')) {
            return;
        }

        $this->source->table('shipment_events')
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows) {
                foreach ($rows as $row) {
                    $this->summary['shipment_events']['processed']++;

                    $shipmentId = null;
                    if (! empty($row->tracking_number)) {
                        $shipmentId = $this->shipmentIdMap[$row->tracking_number] ?? null;
                        if (! $shipmentId && ! $this->dryRun) {
                            $shipmentId = DB::table('shipments')
                                ->where('tracking_number', $row->tracking_number)
                                ->value('id');
                            if ($shipmentId) {
                                $this->shipmentIdMap[$row->tracking_number] = (int) $shipmentId;
                            }
                        }
                    }

                    if (! $shipmentId) {
                        $this->warnings[] = "Shipment event {$row->id} skipped – no matching shipment for tracking {$row->tracking_number}.";

                        continue;
                    }

                    if ($this->dryRun) {
                        continue;
                    }

                    $payload = [
                        'id' => (int) $row->id,
                        'shipment_id' => $shipmentId,
                        'event_code' => $row->event_code ?? null,
                        'event_status' => $row->event_type ?? null,
                        'event_description' => $row->event_text ?? null,
                        'facility' => $row->facility ?? null,
                        'city' => $row->city ?? null,
                        'country_iso2' => $row->country ?? null,
                        'event_occurred_at' => $this->normalizeTimestamp($row->occurred_at ?? null),
                        'payload' => $this->decodeJson($row->raw_payload ?? null),
                        'created_at' => $this->normalizeTimestamp($row->created_at ?? now()),
                    ];

                    $exists = DB::table('shipment_events')->where('id', $payload['id'])->exists();
                    if (! $exists) {
                        DB::table('shipment_events')->insert($payload);
                        $this->summary['shipment_events']['created']++;
                    }
                }
            });
    }

    private function migrateDispatchLists(): void
    {
        if (! $this->sourceTableExists('lieferschein_lists')) {
            return;
        }

        $this->source->table('lieferschein_lists')
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows) {
                foreach ($rows as $row) {
                    $this->summary['dispatch_lists']['processed']++;

                    $status = 'open';
                    if (! empty($row->closed_at)) {
                        $status = 'closed';
                    }

                    $payload = [
                        'id' => (int) $row->id,
                        'reference' => $row->id,
                        'title' => null,
                        'status' => $status,
                        'created_by_user_id' => $this->lookupUserId($row->created_by ?? null),
                        'closed_by_user_id' => $this->lookupUserId($row->closed_by ?? null),
                        'close_requested_at' => $this->normalizeTimestamp($row->close_requested_at ?? null),
                        'close_requested_by' => $row->close_requested_by ?? null,
                        'closed_at' => $this->normalizeTimestamp($row->closed_at ?? null),
                        'exported_at' => $this->normalizeTimestamp($row->closed_at ?? null),
                        'export_filename' => $row->exported_file ?? null,
                        'total_packages' => $this->intOrNull($row->total_pallets ?? null),
                        'total_orders' => null,
                        'total_truck_slots' => null,
                        'notes' => null,
                        'created_at' => $this->normalizeTimestamp($row->created_at ?? now()),
                        'updated_at' => now(),
                    ];

                    if ($this->dryRun) {
                        continue;
                    }

                    $exists = DB::table('dispatch_lists')->where('id', $payload['id'])->first();
                    if ($exists) {
                        DB::table('dispatch_lists')->where('id', $payload['id'])->update(Arr::except($payload, ['id', 'reference', 'created_at']));
                        $this->summary['dispatch_lists']['updated']++;
                    } else {
                        DB::table('dispatch_lists')->insert($payload);
                        $this->summary['dispatch_lists']['created']++;
                    }
                }
            });
    }

    private function migrateDispatchScans(): void
    {
        if (! $this->sourceTableExists('lieferschein_scans')) {
            return;
        }

        $this->source->table('lieferschein_scans')
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows) {
                foreach ($rows as $row) {
                    $this->summary['dispatch_scans']['processed']++;

                    $dispatchId = (int) ($row->list_id ?? 0);
                    if ($dispatchId === 0) {
                        continue;
                    }

                    $orderId = $this->intOrNull($row->order_id ?? null);
                    $mappedOrderId = $orderId ? ($this->orderIdMap[$orderId] ?? null) : null;

                    if (! $mappedOrderId && $orderId && ! $this->dryRun) {
                        $mappedOrderId = DB::table('shipment_orders')
                            ->where('external_order_id', $orderId)
                            ->value('id');
                        if ($mappedOrderId) {
                            $this->orderIdMap[$orderId] = (int) $mappedOrderId;
                        }
                    }

                    if ($this->dryRun) {
                        continue;
                    }

                    $payload = [
                        'id' => (int) $row->id,
                        'dispatch_list_id' => $dispatchId,
                        'barcode' => $row->barcode ?? '',
                        'shipment_order_id' => $mappedOrderId,
                        'captured_by_user_id' => $this->lookupUserId($row->username ?? null),
                        'captured_at' => $this->normalizeTimestamp($row->created_at ?? null),
                        'metadata' => null,
                        'created_at' => $this->normalizeTimestamp($row->created_at ?? now()),
                        'updated_at' => now(),
                    ];

                    $exists = DB::table('dispatch_scans')->where('id', $payload['id'])->exists();
                    if (! $exists) {
                        DB::table('dispatch_scans')->insert($payload);
                        $this->summary['dispatch_scans']['created']++;
                    }
                }
            });
    }

    private function migrateDispatchMetrics(): void
    {
        if (! $this->sourceTableExists('sendung_details')) {
            return;
        }

        $this->source->table('sendung_details')
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows) {
                foreach ($rows as $row) {
                    $this->summary['dispatch_metrics']['processed']++;

                    $dispatchId = (int) ($row->list_id ?? 0);
                    if ($dispatchId === 0) {
                        continue;
                    }

                    $payload = [
                        'dispatch_list_id' => $dispatchId,
                        'total_orders' => $this->intOrNull($row->anzahl_sendungen ?? null),
                        'total_packages' => $this->intOrNull($row->sendungen ?? null),
                        'total_items' => null,
                        'total_truck_slots' => null,
                        'metrics' => $this->encodeJson([
                            'tische_pro_palette' => $row->tische_pro_palette ?? null,
                            'created_at' => $row->created_at ?? null,
                        ]),
                        'created_at' => $this->normalizeTimestamp($row->created_at ?? now()),
                        'updated_at' => now(),
                    ];

                    if ($this->dryRun) {
                        continue;
                    }

                    DB::table('dispatch_metrics')->updateOrInsert(
                        ['dispatch_list_id' => $dispatchId],
                        Arr::except($payload, ['dispatch_list_id', 'created_at']) + [
                            'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        ]
                    );
                    $this->summary['dispatch_metrics']['created']++;
                }
            });
    }

    private function migrateAuditLogs(): void
    {
        if (! $this->sourceTableExists('audits')) {
            return;
        }

        $this->source->table('audits')
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows) {
                foreach ($rows as $row) {
                    $this->summary['audit_logs']['processed']++;

                    if ($this->dryRun) {
                        continue;
                    }

                    $payload = [
                        'id' => (int) $row->id,
                        'actor_type' => 'user',
                        'actor_id' => $row->username ?? null,
                        'actor_name' => $row->username ?? null,
                        'action' => $row->action ?? 'unknown',
                        'context' => $this->decodeDetails($row->details ?? null),
                        'ip_address' => $row->ip ?? null,
                        'user_agent' => null,
                        'created_at' => $this->normalizeTimestamp($row->created_at ?? now()),
                    ];

                    $exists = DB::table('audit_logs')->where('id', $payload['id'])->exists();
                    if (! $exists) {
                        DB::table('audit_logs')->insert($payload);
                        $this->summary['audit_logs']['created']++;
                    }
                }
            });
    }

    private function migrateSystemSettings(): void
    {
        if (! $this->sourceTableExists('config')) {
            return;
        }

        $rows = $this->source->table('config')->get();

        foreach ($rows as $row) {
            $this->summary['system_settings']['processed']++;

            $value = $row->value ?? null;
            $payload = [
                'setting_value' => $value,
                'value_type' => $this->inferValueType($value),
                'updated_by_user_id' => null,
                'updated_at' => now(),
            ];

            if ($this->dryRun) {
                continue;
            }

            $exists = DB::table('system_settings')->where('setting_key', $row->key)->first();
            if ($exists) {
                DB::table('system_settings')->where('setting_key', $row->key)->update($payload);
                $this->summary['system_settings']['updated']++;
            } else {
                DB::table('system_settings')->insert($payload + [
                    'setting_key' => $row->key,
                ]);
                $this->summary['system_settings']['created']++;
            }
        }
    }

    private function migrateTrackingEventsLog(): void
    {
        if (! $this->sourceTableExists('tracking_events_log')) {
            return;
        }

        $this->source->table('tracking_events_log')
            ->orderBy('id')
            ->chunk($this->chunkSize, function (Collection $rows) {
                foreach ($rows as $row) {
                    $this->summary['tracking_events_log']['processed']++;

                    if ($this->dryRun) {
                        continue;
                    }

                    DB::table('system_jobs')->insert([
                        'job_name' => $row->action ?? 'tracking_event',
                        'job_type' => 'tracking_event',
                        'run_context' => null,
                        'status' => Str::contains(Str::lower((string) $row->message), 'fehler') ? 'failed' : 'completed',
                        'scheduled_at' => null,
                        'started_at' => $this->normalizeTimestamp($row->created_at ?? null),
                        'finished_at' => $this->normalizeTimestamp($row->created_at ?? null),
                        'duration_ms' => null,
                        'payload' => $this->encodeJson(null),
                        'result' => $this->encodeJson(['message' => $row->message ?? null]),
                        'error_message' => null,
                        'created_at' => $this->normalizeTimestamp($row->created_at ?? now()),
                        'updated_at' => now(),
                    ]);
                    $this->summary['tracking_events_log']['created']++;
                }
            });
    }

    private function renderSummary(): void
    {
        $this->info('');
        $this->info('Migration summary:');
        foreach ($this->summary as $section => $data) {
            $line = ucfirst(str_replace('_', ' ', $section)).': ';
            $line .= 'processed '.($data['processed'] ?? 0);
            if (array_key_exists('created', $data)) {
                $line .= ', created '.$data['created'];
            }
            if (array_key_exists('updated', $data)) {
                $line .= ', updated '.$data['updated'];
            }
            $this->line('- '.$line);
        }
    }

    private function renderWarnings(): void
    {
        if (empty($this->warnings)) {
            return;
        }

        $this->warn('');
        $this->warn('Warnings:');
        foreach ($this->warnings as $warning) {
            $this->warn('• '.$warning);
        }
    }

    private function lookupUserId(?string $username): ?int
    {
        if (! $username) {
            return null;
        }

        return $this->userMap[$username] ?? null;
    }

    private function intOrNull($value): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function decimalOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeTimestamp($value): ?string
    {
        if (! $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return $value;
    }

    private function normalizeWeight($value, $unit): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $numeric = (float) $value;
        $unit = strtolower((string) $unit);

        return match ($unit) {
            'g', 'gram', 'grams' => round($numeric / 1000, 3),
            'kg', 'kilogram', 'kilograms', '' => round($numeric, 3),
            'lb', 'lbs', 'pound', 'pounds' => round($numeric * 0.453592, 3),
            default => round($numeric, 3),
        };
    }

    private function normalizeVolume($value, $unit): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $numeric = (float) $value;
        $unit = strtolower((string) $unit);

        return match ($unit) {
            'm3', 'cbm' => round($numeric * 1000, 3), // cubic meters to dm3
            'dm3', '' => round($numeric, 3),
            default => round($numeric, 3),
        };
    }

    private function decodeJson($value)
    {
        if (! $value) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $decoded = json_decode((string) $value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }

    private function encodeJson($payload): ?string
    {
        if ($payload === null) {
            return null;
        }
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function mergeMetadata($existing, $incoming): ?string
    {
        $existingArr = $existing ? json_decode($existing, true) : [];
        if (! is_array($existingArr)) {
            $existingArr = [];
        }
        $incomingArr = $incoming ? json_decode($incoming, true) : [];
        if (! is_array($incomingArr)) {
            $incomingArr = [];
        }
        $merged = array_filter(array_merge($existingArr, $incomingArr), fn ($value) => $value !== null && $value !== '');
        if (empty($merged)) {
            return null;
        }

        return json_encode($merged, JSON_UNESCAPED_UNICODE);
    }

    private function decodeDetails($details): ?string
    {
        if (! $details) {
            return null;
        }
        $decoded = json_decode((string) $details, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return json_encode(['text' => $details], JSON_UNESCAPED_UNICODE);
    }

    private function inferValueType($value): string
    {
        if ($value === null) {
            return 'string';
        }
        if (is_numeric($value)) {
            return Str::contains((string) $value, '.') ? 'float' : 'int';
        }
        $lower = Str::lower((string) $value);
        if (in_array($lower, ['true', 'false', '1', '0'], true)) {
            return 'bool';
        }

        return 'string';
    }

    private function sourceTableExists(string $table): bool
    {
        // Schema::connection() funktioniert auch mit ConnectionInterface,
        // während $connection->getSchemaBuilder() nur auf der konkreten
        // Illuminate\Database\Connection definiert ist.
        return Schema::connection('ax4_source')->hasTable($table);
    }

    public function warn($string, $verbosity = null): void
    {
        parent::warn($string, $verbosity);
    }
}
