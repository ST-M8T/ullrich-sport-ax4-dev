<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\FreightProfileService;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\Exceptions\FreightProfileNotFoundException;
use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class FreightProfileServiceTest extends TestCase
{
    private FulfillmentFreightProfileRepository&MockInterface $repository;

    private FreightProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(FulfillmentFreightProfileRepository::class);
        $this->service = new FreightProfileService($this->repository);
    }

    public function test_create_normalises_and_persists_profile(): void
    {
        $profile = $this->freightProfile(7);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['shipping_profile_id'] === 7
                    && $payload['label'] === 'Standard'
                    && $payload['dhl_product_code'] === 'V01PAK'
                    && $payload['dhl_product_id'] === 'V01PAK'
                    && $payload['account_number'] === '22222222220101';
            }))
            ->andReturn($profile);

        $result = $this->service->create([
            'shipping_profile_id' => '7',
            'label' => '  Standard  ',
            'dhl_product_code' => 'v01pak',
            'account_number' => '  22222222220101  ',
        ]);

        $this->assertSame($profile, $result);
    }

    public function test_create_throws_when_shipping_profile_id_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field shipping_profile_id.');

        $this->service->create([
            'label' => 'Standard',
        ]);
    }

    public function test_create_collapses_empty_service_codes_to_null(): void
    {
        $profile = $this->freightProfile(8);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['dhl_default_service_codes'] === null
                    && $payload['shipping_method_mapping'] === null;
            }))
            ->andReturn($profile);

        $this->service->create([
            'shipping_profile_id' => 8,
            'dhl_default_service_codes' => [],
            'shipping_method_mapping' => [],
        ]);
    }

    public function test_update_dhl_mappings_passes_normalised_payload(): void
    {
        $profile = $this->freightProfile(9);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn (Identifier $id) => $id->toInt() === 9),
                Mockery::on(function (array $payload): bool {
                    return $payload['dhl_product_id'] === 'V01PAK'
                        && $payload['dhl_default_service_codes'] === ['PREMIUM']
                        && $payload['account_number'] === '12345';
                }),
            )
            ->andReturn($profile);

        $this->service->updateDhlMappings(9, 'V01PAK', ['PREMIUM'], [], '  12345  ');
    }

    public function test_delete_throws_when_profile_missing(): void
    {
        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->andReturnNull();

        $this->expectException(FreightProfileNotFoundException::class);

        $this->service->delete(404);
    }

    public function test_delete_calls_repository_when_profile_exists(): void
    {
        $profile = $this->freightProfile(15);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->andReturn($profile);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn (Identifier $id) => $id->toInt() === 15));

        $this->service->delete(15);
    }

    private function freightProfile(int $id): FulfillmentFreightProfile
    {
        return FulfillmentFreightProfile::hydrate(
            Identifier::fromInt($id),
            'Standard',
            null,
            [],
            [],
            null,
            'V01PAK',
            null,
        );
    }
}
