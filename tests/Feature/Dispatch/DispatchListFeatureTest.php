<?php

declare(strict_types=1);

namespace Tests\Feature\Dispatch;

use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Dispatch\DispatchScan;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DispatchListFeatureTest extends TestCase
{
    use RefreshDatabase;

    private DispatchListRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DispatchListRepository::class);
    }

    public function test_it_lists_dispatch_lists(): void
    {
        $user = $this->createUser();
        $list = $this->makeDispatchAggregate(scanCount: 2);

        $this->actingAs($user);

        $response = $this->get('/admin/dispatch/lists');

        $response->assertOk();
        // Anwendung verwendet die deutsche Bezeichnung "Kommissionierlisten" (Ubiquitous Language).
        $response->assertSee('Kommissionierlisten');
        $response->assertSee((string) $list->reference());
        $response->assertSee('SCANS (2)');
        $response->assertSee('SCHLIESSEN');
        $response->assertSee('EXPORT');
    }

    public function test_it_filters_dispatch_lists_by_status(): void
    {
        $user = $this->createUser();
        $this->makeDispatchAggregate(attributes: ['status' => 'open', 'reference' => 'OPEN-REF']);
        $closed = $this->makeDispatchAggregate(attributes: ['status' => 'closed', 'reference' => 'CLOSED-REF']);

        $this->actingAs($user);

        $response = $this->get('/admin/dispatch/lists?status=closed');

        $response->assertOk();
        $response->assertSee('CLOSED-REF');
        $response->assertDontSee('OPEN-REF');
    }

    public function test_it_closes_a_dispatch_list(): void
    {
        $user = $this->createUser();
        $list = $this->makeDispatchAggregate(attributes: ['status' => 'open', 'reference' => 'CLOSE-ME']);

        $this->actingAs($user);

        $response = $this->post(route('dispatch-lists.close', ['list' => $list->id()->toInt()]), [
            'export_filename' => 'closed-list.csv',
        ]);

        $response->assertRedirect(route('dispatch-lists'));
        $response->assertSessionHas('success');

        $updated = $this->repository->getById($list->id());
        $this->assertNotNull($updated);
        $this->assertSame('closed', $updated->status());
        $this->assertSame('closed-list.csv', $updated->exportFilename());
        $this->assertNotNull($updated->closedAt());
    }

    public function test_it_rejects_invalid_export_filename_when_closing(): void
    {
        $user = $this->createUser();
        $list = $this->makeDispatchAggregate(attributes: ['status' => 'open', 'reference' => 'INVALID-EXPORT']);

        $this->actingAs($user);

        $response = $this->post(route('dispatch-lists.close', ['list' => $list->id()->toInt()]), [
            'export_filename' => 'invalid.txt',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['export_filename']);

        $fresh = $this->repository->getById($list->id());
        $this->assertNotNull($fresh);
        $this->assertSame('open', $fresh->status());
        $this->assertNull($fresh->exportFilename());
    }

    public function test_it_exports_a_dispatch_list(): void
    {
        $user = $this->createUser();
        $list = $this->makeDispatchAggregate(attributes: ['status' => 'closed', 'reference' => 'READY-EXPORT', 'export_filename' => null]);

        $this->actingAs($user);

        $response = $this->post(route('dispatch-lists.export', ['list' => $list->id()->toInt()]), [
            'export_filename' => 'final-export.csv',
        ]);

        $response->assertRedirect(route('dispatch-lists'));
        $response->assertSessionHas('success');

        $updated = $this->repository->getById($list->id());
        $this->assertNotNull($updated);
        $this->assertSame('exported', $updated->status());
        $this->assertSame('final-export.csv', $updated->exportFilename());
        $this->assertNotNull($updated->exportedAt());
    }

    public function test_it_returns_scan_payload_over_endpoint(): void
    {
        $user = $this->createUser();
        $list = $this->makeDispatchAggregate(scanCount: 1);

        $this->actingAs($user);

        $response = $this->getJson(route('dispatch-lists.scans', ['list' => $list->id()->toInt()]));

        $response->assertOk();
        $response->assertJsonPath('list_id', $list->id()->toInt());
        $response->assertJsonPath('scan_count', 1);
        $response->assertJsonCount(1, 'scans');
        $response->assertJsonPath('scans.0.barcode', 'SCAN-'.$list->id()->toInt().'-1');
    }

    private function createUser(array $overrides = []): UserModel
    {
        $username = $overrides['username'] ?? ('dispatch_'.Str::lower(Str::random(8)));

        $defaults = [
            'username' => $username,
            'display_name' => $overrides['display_name'] ?? 'Dispatch Admin',
            'email' => $overrides['email'] ?? $username.'@example.test',
            'password_hash' => bcrypt('password'),
            'role' => $overrides['role'] ?? 'operations',
            'must_change_password' => false,
            'disabled' => false,
            'last_login_at' => null,
        ];

        $attributes = array_merge($defaults, $overrides);

        return UserModel::query()->create($attributes);
    }

    protected function makeDispatchAggregate(array $attributes = [], int $scanCount = 1): DispatchList
    {
        $id = $this->repository->nextListIdentity();
        $now = new DateTimeImmutable;
        $status = $attributes['status'] ?? DispatchList::STATUS_OPEN;
        $reference = $attributes['reference'] ?? 'REF-'.$id->toInt();
        $title = $attributes['title'] ?? 'Dispatch '.$id->toInt();
        $notes = $attributes['notes'] ?? null;
        $exportFilename = $attributes['export_filename'] ?? null;
        $closedByIdentifier = null;

        if ($status !== DispatchList::STATUS_OPEN) {
            $closedByIdentifier = isset($attributes['closed_by'])
                ? Identifier::fromInt((int) $attributes['closed_by'])
                : Identifier::fromInt($this->createUser()->getKey());
        }

        $metricsConfig = $attributes['metrics'] ?? [];
        $metrics = DispatchMetrics::hydrate(
            $metricsConfig['total_orders'] ?? 5,
            $metricsConfig['total_packages'] ?? 3,
            $metricsConfig['total_items'] ?? 12,
            $metricsConfig['total_truck_slots'] ?? 2,
            $metricsConfig['metrics'] ?? ['volume_dm3' => 42.5]
        );

        $list = DispatchList::hydrate(
            $id,
            $reference,
            $title,
            DispatchList::STATUS_OPEN,
            isset($attributes['created_by']) ? Identifier::fromInt((int) $attributes['created_by']) : null,
            null,
            null,
            null,
            null,
            null,
            null,
            $metrics->totalPackages(),
            $metrics->totalOrders(),
            $metrics->totalTruckSlots(),
            $notes,
            $metrics,
            [],
            $now,
            $now,
        );

        $current = $list;

        $this->repository->save($current);

        for ($i = 0; $i < $scanCount; $i++) {
            $scanId = $this->repository->nextScanIdentity();
            $capturedAt = $now;
            $scan = DispatchScan::hydrate(
                $scanId,
                $id,
                sprintf('SCAN-%d-%d', $id->toInt(), $i + 1),
                null,
                null,
                $capturedAt,
                [],
                $capturedAt,
                $capturedAt,
            );

            $current = $current->recordScan($scan, $capturedAt);
            $current = $this->repository->appendScan($current, $scan);
        }

        if ($status !== DispatchList::STATUS_OPEN) {
            $closedBy = $closedByIdentifier ?? Identifier::fromInt($this->createUser()->getKey());
            $current = $current->close($closedBy, $exportFilename, $now);
            $this->repository->save($current);

            if ($status === DispatchList::STATUS_EXPORTED) {
                $finalFilename = $exportFilename ?? sprintf('export-%d.csv', $id->toInt());
                $current = $current->export($closedBy, $finalFilename, $now);
                $this->repository->save($current);
            }
        }

        $fresh = $this->repository->getById($id);

        if (! $fresh) {
            $this->fail('Dispatch list could not be retrieved after creation.');
        }

        return $fresh;
    }
}
