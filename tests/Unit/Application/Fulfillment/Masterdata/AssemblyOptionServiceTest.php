<?php

namespace Tests\Unit\Application\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\AssemblyOptionService;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentAssemblyOptionRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class AssemblyOptionServiceTest extends TestCase
{
    private FulfillmentAssemblyOptionRepository&MockInterface $assemblyOptions;

    private FulfillmentPackagingProfileRepository&MockInterface $packagingProfiles;

    private AssemblyOptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assemblyOptions = Mockery::mock(FulfillmentAssemblyOptionRepository::class);
        $this->packagingProfiles = Mockery::mock(FulfillmentPackagingProfileRepository::class);

        $this->service = new AssemblyOptionService($this->assemblyOptions, $this->packagingProfiles);
    }

    public function test_create_validates_packaging_and_persists_option(): void
    {
        $packaging = $this->packagingProfile(Identifier::fromInt(5));
        $option = $this->assemblyOption(Identifier::fromInt(11), 700, 5, 15.5);

        $this->packagingProfiles
            ->shouldReceive('getById')
            ->once()
            ->with(Mockery::on(fn (Identifier $id): bool => $id->toInt() === 5))
            ->andReturn($packaging);

        $this->assemblyOptions
            ->shouldReceive('create')
            ->once()
            ->with([
                'assembly_item_id' => 700,
                'assembly_packaging_id' => 5,
                'assembly_weight_kg' => 12.4,
                'description' => 'Preassembly',
            ])
            ->andReturn($option);

        $created = $this->service->create([
            'assembly_item_id' => '700',
            'assembly_packaging_id' => '5',
            'assembly_weight_kg' => '12.4',
            'description' => 'Preassembly',
        ]);

        $this->assertSame(700, $created->assemblyItemId());
    }

    public function test_create_requires_required_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field assembly_item_id.');

        $this->service->create([
            'assembly_packaging_id' => 1,
        ]);
    }

    public function test_create_throws_when_packaging_missing(): void
    {
        $this->packagingProfiles
            ->shouldReceive('getById')
            ->once()
            ->with(Mockery::on(fn (Identifier $id): bool => $id->toInt() === 5))
            ->andReturnNull();

        $this->expectException(ModelNotFoundException::class);

        $this->service->create([
            'assembly_item_id' => 100,
            'assembly_packaging_id' => 5,
        ]);
    }

    public function test_update_validates_optional_packaging_when_present(): void
    {
        $existing = $this->assemblyOption(Identifier::fromInt(12), 500, 5, 10.0);
        $packaging = $this->packagingProfile(Identifier::fromInt(7));

        $this->packagingProfiles
            ->shouldReceive('getById')
            ->once()
            ->with(Mockery::on(fn (Identifier $id): bool => $id->toInt() === 7))
            ->andReturn($packaging);

        $this->assemblyOptions
            ->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn (Identifier $id): bool => $id->toInt() === 12),
                [
                    'assembly_weight_kg' => null,
                    'assembly_packaging_id' => 7,
                ]
            )
            ->andReturn($existing);

        $updated = $this->service->update(12, [
            'assembly_packaging_id' => '7',
            'assembly_weight_kg' => '',
        ]);

        $this->assertInstanceOf(FulfillmentAssemblyOption::class, $updated);
    }

    public function test_delete_throws_when_option_missing(): void
    {
        $identifier = Identifier::fromInt(25);

        $this->assemblyOptions
            ->shouldReceive('getById')
            ->once()
            ->with(Mockery::on(fn (Identifier $id): bool => $id->toInt() === 25))
            ->andReturnNull();

        $this->expectException(ModelNotFoundException::class);

        $this->service->delete(25);
    }

    private function packagingProfile(Identifier $id): FulfillmentPackagingProfile
    {
        return FulfillmentPackagingProfile::hydrate(
            $id,
            'Eurobox',
            'PKG-1',
            600,
            400,
            300,
            2,
            10,
            10,
            3,
            2,
            null,
        );
    }

    private function assemblyOption(Identifier $id, int $itemId, int $packagingId, ?float $weight): FulfillmentAssemblyOption
    {
        return FulfillmentAssemblyOption::hydrate(
            $id,
            $itemId,
            Identifier::fromInt($packagingId),
            $weight,
            'Assembly option',
        );
    }
}
