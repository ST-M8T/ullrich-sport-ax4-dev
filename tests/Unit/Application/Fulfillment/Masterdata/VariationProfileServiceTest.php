<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\VariationProfileService;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentAssemblyOptionRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentVariationProfileRepository;
use App\Domain\Fulfillment\Masterdata\Exceptions\AssemblyOptionNotFoundException;
use App\Domain\Fulfillment\Masterdata\Exceptions\PackagingProfileNotFoundException;
use App\Domain\Fulfillment\Masterdata\Exceptions\VariationProfileNotFoundException;
use App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class VariationProfileServiceTest extends TestCase
{
    private FulfillmentVariationProfileRepository&MockInterface $variations;

    private FulfillmentPackagingProfileRepository&MockInterface $packagings;

    private FulfillmentAssemblyOptionRepository&MockInterface $assemblyOptions;

    private VariationProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variations = Mockery::mock(FulfillmentVariationProfileRepository::class);
        $this->packagings = Mockery::mock(FulfillmentPackagingProfileRepository::class);
        $this->assemblyOptions = Mockery::mock(FulfillmentAssemblyOptionRepository::class);

        $this->service = new VariationProfileService(
            $this->variations,
            $this->packagings,
            $this->assemblyOptions,
        );
    }

    public function test_create_validates_packaging_exists_and_persists(): void
    {
        $this->packagings
            ->shouldReceive('getById')
            ->once()
            ->with(Mockery::on(fn (Identifier $id) => $id->toInt() === 4))
            ->andReturn($this->packagingProfile(4));

        $this->variations
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['item_id'] === 200
                    && $payload['default_packaging_id'] === 4
                    && $payload['default_state'] === 'kit'
                    && $payload['default_weight_kg'] === 1.5;
            }))
            ->andReturn($this->variationProfile(1));

        $this->service->create([
            'item_id' => '200',
            'default_state' => '  KIT  ',
            'default_packaging_id' => '4',
            'default_weight_kg' => '1.5',
        ]);
    }

    public function test_create_throws_when_packaging_missing(): void
    {
        $this->packagings
            ->shouldReceive('getById')
            ->once()
            ->andReturnNull();

        $this->expectException(PackagingProfileNotFoundException::class);

        $this->service->create([
            'item_id' => 100,
            'default_state' => 'kit',
            'default_packaging_id' => 99,
        ]);
    }

    public function test_create_throws_when_required_field_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field item_id.');

        $this->service->create([
            'default_state' => 'kit',
            'default_packaging_id' => 1,
        ]);
    }

    public function test_create_validates_assembly_option_when_present(): void
    {
        $this->packagings
            ->shouldReceive('getById')
            ->once()
            ->andReturn($this->packagingProfile(4));

        $this->assemblyOptions
            ->shouldReceive('getById')
            ->once()
            ->with(Mockery::on(fn (Identifier $id) => $id->toInt() === 33))
            ->andReturnNull();

        $this->expectException(AssemblyOptionNotFoundException::class);

        $this->service->create([
            'item_id' => 100,
            'default_state' => 'assembled',
            'default_packaging_id' => 4,
            'assembly_option_id' => '33',
        ]);
    }

    public function test_delete_throws_when_variation_missing(): void
    {
        $this->variations
            ->shouldReceive('getById')
            ->once()
            ->andReturnNull();

        $this->expectException(VariationProfileNotFoundException::class);

        $this->service->delete(404);
    }

    private function packagingProfile(int $id): FulfillmentPackagingProfile
    {
        return FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt($id),
            'Eurobox',
            'EUR-1',
            600, 400, 300, 2, 10, 10, 3, 2, null,
        );
    }

    private function variationProfile(int $id): FulfillmentVariationProfile
    {
        return FulfillmentVariationProfile::hydrate(
            Identifier::fromInt($id),
            200,
            null,
            null,
            'kit',
            Identifier::fromInt(4),
            1.5,
            null,
        );
    }
}
