<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Persistence\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlAdditionalServiceRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceCategory;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentDhlAdditionalServiceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DhlAdditionalServiceRepository $repository;
    private AuditActor $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(DhlAdditionalServiceRepository::class);
        $this->actor = AuditActor::system('test');
    }

    public function test_save_and_find_roundtrip(): void
    {
        $svc = $this->makeService('COD');
        $this->repository->save($svc, $this->actor);

        $loaded = $this->repository->findByCode('COD');
        self::assertNotNull($loaded);
        self::assertSame('COD', $loaded->code());
        self::assertSame(DhlServiceCategory::DELIVERY, $loaded->category());
    }

    public function test_find_by_category(): void
    {
        $this->repository->save($this->makeService('COD', DhlServiceCategory::DELIVERY), $this->actor);
        $this->repository->save($this->makeService('SMS', DhlServiceCategory::NOTIFICATION), $this->actor);

        $delivery = iterator_to_array(
            $this->repository->findByCategory(DhlServiceCategory::DELIVERY),
            false,
        );
        $codes = array_map(static fn (DhlAdditionalService $s) => $s->code(), $delivery);

        self::assertContains('COD', $codes);
        self::assertNotContains('SMS', $codes);
    }

    public function test_soft_deprecate_and_restore(): void
    {
        $this->repository->save($this->makeService('SMS'), $this->actor);
        $this->repository->softDeprecate('SMS', $this->actor);

        $loaded = $this->repository->findByCode('SMS');
        self::assertNotNull($loaded);
        self::assertTrue($loaded->isDeprecated());

        $this->repository->restore('SMS', $this->actor);

        $loaded = $this->repository->findByCode('SMS');
        self::assertNotNull($loaded);
        self::assertFalse($loaded->isDeprecated());
    }

    public function test_find_all_active_excludes_deprecated(): void
    {
        $this->repository->save($this->makeService('AAA'), $this->actor);
        $this->repository->save($this->makeService('BBB'), $this->actor);
        $this->repository->softDeprecate('BBB', $this->actor);

        $active = iterator_to_array($this->repository->findAllActive(), false);
        $codes = array_map(static fn ($s) => $s->code(), $active);

        self::assertContains('AAA', $codes);
        self::assertNotContains('BBB', $codes);
    }

    private function makeService(
        string $code,
        DhlServiceCategory $category = DhlServiceCategory::DELIVERY,
    ): DhlAdditionalService {
        return new DhlAdditionalService(
            code: $code,
            name: 'Service ' . $code,
            description: 'desc',
            category: $category,
            parameterSchema: JsonSchema::fromArray([
                'type' => 'object',
                'properties' => ['amount' => ['type' => 'number']],
            ]),
            deprecatedAt: null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }
}
