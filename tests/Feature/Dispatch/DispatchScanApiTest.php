<?php

declare(strict_types=1);

namespace Tests\Feature\Dispatch;

use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Domain\Dispatch\DispatchList;
use App\Domain\Dispatch\DispatchMetrics;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use DateInterval;
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
    }

    public function test_it_captures_scan_via_api(): void
    {
        $list = $this->makeDispatchList();

        $response = $this->postJson(
            sprintf('/api/v1/dispatch-lists/%d/scans', $list->id()->toInt()),
            [
                'barcode' => '  PKG-200  ',
                'metadata' => ['lane' => 'A1'],
                'captured_at' => '2024-04-01T10:15:00+00:00',
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('scan.barcode', 'PKG-200');

        $fresh = $this->repository->getById($list->id());
        $this->assertNotNull($fresh);
        $this->assertSame(1, $fresh->scanCount());
        $this->assertGreaterThan($list->updatedAt(), $fresh->updatedAt());
    }

    public function test_it_rejects_captures_on_closed_lists(): void
    {
        $list = $this->makeDispatchList(status: DispatchList::STATUS_CLOSED);

        $response = $this->postJson(
            sprintf('/api/v1/dispatch-lists/%d/scans', $list->id()->toInt()),
            ['barcode' => 'PKG-200']
        );

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Cannot add scans to a closed dispatch list.');
    }

    private function makeDispatchList(string $status = DispatchList::STATUS_OPEN): DispatchList
    {
        $id = $this->repository->nextListIdentity();
        $createdAt = new DateTimeImmutable('-1 hour');
        $closedAt = null;
        $closedBy = null;
        $exportedAt = null;
        $exportFilename = null;

        if ($status !== DispatchList::STATUS_OPEN) {
            $closedAt = $createdAt->add(new DateInterval('PT30M'));
            $user = UserModel::factory()->create();
            $closedBy = Identifier::fromInt((int) $user->getKey());
        }

        if ($status === DispatchList::STATUS_EXPORTED) {
            $exportedAt = $closedAt?->add(new DateInterval('PT15M')) ?? $createdAt->add(new DateInterval('PT45M'));
            $exportFilename = sprintf('export-%d.csv', $id->toInt());
        }

        $updatedAt = $exportedAt ?? $closedAt ?? $createdAt;

        $list = DispatchList::hydrate(
            $id,
            'REF-'.$id->toInt(),
            'API List',
            $status,
            null,
            $closedBy,
            null,
            null,
            $closedAt,
            $exportedAt,
            $exportFilename,
            null,
            null,
            null,
            null,
            DispatchMetrics::hydrate(0, 0, 0, 0, []),
            [],
            $createdAt,
            $updatedAt,
        );

        $this->repository->save($list);

        $fresh = $this->repository->getById($id);

        return $fresh ?? $list;
    }
}
