<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\PackagingProfileService;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\Exceptions\PackagingProfileNotFoundException;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class PackagingProfileServiceTest extends TestCase
{
    private FulfillmentPackagingProfileRepository&MockInterface $repository;

    private PackagingProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(FulfillmentPackagingProfileRepository::class);
        $this->service = new PackagingProfileService($this->repository);
    }

    public function test_create_coerces_numeric_strings_to_int(): void
    {
        $profile = $this->packagingProfile(1);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['length_mm'] === 600
                    && $payload['width_mm'] === 400
                    && $payload['height_mm'] === 300
                    && $payload['truck_slot_units'] === 2
                    && $payload['package_name'] === 'Eurobox'
                    && $payload['packaging_code'] === 'EUR-1'
                    && $payload['notes'] === null;
            }))
            ->andReturn($profile);

        $this->service->create([
            'package_name' => '  Eurobox  ',
            'packaging_code' => '  EUR-1  ',
            'length_mm' => '600',
            'width_mm' => '400',
            'height_mm' => '300',
            'truck_slot_units' => '2',
            'max_units_per_pallet_same_recipient' => '10',
            'max_units_per_pallet_mixed_recipient' => '10',
            'max_stackable_pallets_same_recipient' => '3',
            'max_stackable_pallets_mixed_recipient' => '2',
            'notes' => '   ',
        ]);
    }

    public function test_update_delegates_to_repository(): void
    {
        $profile = $this->packagingProfile(5);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn (Identifier $id) => $id->toInt() === 5),
                Mockery::on(fn (array $p) => $p['length_mm'] === 500 && $p['package_name'] === 'Box'),
            )
            ->andReturn($profile);

        $this->service->update(5, [
            'package_name' => 'Box',
            'length_mm' => '500',
        ]);
    }

    public function test_delete_throws_when_profile_missing(): void
    {
        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->andReturnNull();

        $this->expectException(PackagingProfileNotFoundException::class);

        $this->service->delete(123);
    }

    public function test_delete_calls_repository_when_profile_exists(): void
    {
        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->andReturn($this->packagingProfile(10));

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn (Identifier $id) => $id->toInt() === 10));

        $this->service->delete(10);
    }

    private function packagingProfile(int $id): FulfillmentPackagingProfile
    {
        return FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt($id),
            'Eurobox',
            'EUR-1',
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
}
