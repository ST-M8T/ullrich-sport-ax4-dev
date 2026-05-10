<?php

declare(strict_types=1);

namespace Tests\Feature\Dispatch;

use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchScanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DispatchIdentitySequenceTest extends TestCase
{
    use RefreshDatabase;

    private DispatchListRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DispatchListRepository::class);
    }

    public function test_next_list_identity_respects_existing_records(): void
    {
        $existing = DispatchListModel::factory()->create();
        $expected_first = $existing->getKey() + 1;

        $first = $this->repository->nextListIdentity();
        $second = $this->repository->nextListIdentity();

        $this->assertSame($expected_first, $first->toInt());
        $this->assertSame($expected_first + 1, $second->toInt());
    }

    public function test_next_scan_identity_respects_existing_records(): void
    {
        $list = DispatchListModel::factory()->create();
        $existing_scan = DispatchScanModel::factory()->create([
            'dispatch_list_id' => $list->getKey(),
        ]);
        $expected_first = $existing_scan->getKey() + 1;

        $first = $this->repository->nextScanIdentity();
        $second = $this->repository->nextScanIdentity();

        $this->assertSame($expected_first, $first->toInt());
        $this->assertSame($expected_first + 1, $second->toInt());
    }
}
