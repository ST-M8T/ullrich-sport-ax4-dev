<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Dispatch\DispatchScan;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DispatchScanApiTest extends TestCase
{
    use RefreshDatabase;

    private DispatchListRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DispatchListRepository::class);
        config(['services.api.key' => 'test-key']);
    }

    public function test_it_returns_scans_for_dispatch_list(): void
    {
        $aggregate = $this->createListWithScan();

        $response = $this->getJson(
            sprintf('/api/v1/dispatch-lists/%d/scans', $aggregate->id()->toInt()),
            ['X-API-Key' => 'test-key']
        );

        $response->assertOk();
        $response->assertJsonPath('list_id', $aggregate->id()->toInt());
        $response->assertJsonPath('scan_count', 1);
        $response->assertJsonPath('scans.0.barcode', 'PKG-001');

        $capturedAt = $response->json('scans.0.captured_at');
        $this->assertNotNull($capturedAt);
        $this->assertStringContainsString('T', $capturedAt);
    }

    private function createListWithScan(): DispatchList
    {
        $listId = $this->repository->nextListIdentity();
        $createdAt = new DateTimeImmutable('-1 hour');

        $list = DispatchList::hydrate(
            $listId,
            'REF-'.$listId->toInt(),
            'API Scans',
            DispatchList::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            DispatchMetrics::hydrate(0, 0, 0, 0, []),
            [],
            $createdAt,
            $createdAt,
        );

        $this->repository->save($list);

        $scanId = $this->repository->nextScanIdentity();
        $capturedAt = new DateTimeImmutable('-30 minutes');

        $scan = DispatchScan::hydrate(
            $scanId,
            $listId,
            'PKG-001',
            null,
            null,
            $capturedAt,
            ['lane' => 'A'],
            $capturedAt,
            $capturedAt,
        );

        $updatedList = $list->recordScan($scan, $capturedAt);
        $this->repository->appendScan($updatedList, $scan);

        return $this->repository->getById($listId) ?? $updatedList;
    }
}
