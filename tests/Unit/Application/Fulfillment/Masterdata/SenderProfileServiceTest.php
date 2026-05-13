<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\SenderProfileService;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Masterdata\Exceptions\SenderProfileNotFoundException;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SenderProfileServiceTest extends TestCase
{
    private FulfillmentSenderProfileRepository&MockInterface $repository;

    private SenderProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(FulfillmentSenderProfileRepository::class);
        $this->service = new SenderProfileService($this->repository);
    }

    public function test_create_trims_required_fields_and_blanks_optional(): void
    {
        $profile = $this->senderProfile(1);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['sender_code'] === 'main'
                    && $payload['display_name'] === 'Ullrich'
                    && $payload['company_name'] === 'Ullrich Sport'
                    && $payload['street_name'] === 'Hauptstr.'
                    && $payload['postal_code'] === '12345'
                    && $payload['city'] === 'Berlin'
                    && $payload['country_iso2'] === 'DE'
                    && $payload['contact_person'] === null
                    && $payload['phone'] === null;
            }))
            ->andReturn($profile);

        $this->service->create([
            'sender_code' => '  main  ',
            'display_name' => '  Ullrich  ',
            'company_name' => '  Ullrich Sport  ',
            'street_name' => '  Hauptstr.  ',
            'postal_code' => '  12345  ',
            'city' => '  Berlin  ',
            'country_iso2' => 'DE',
            'contact_person' => '   ',
            'phone' => null,
        ]);
    }

    public function test_create_throws_when_required_field_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field postal_code.');

        $this->service->create([
            'sender_code' => 'main',
            'display_name' => 'Ullrich',
            'company_name' => 'Ullrich Sport',
            'street_name' => 'Hauptstr.',
            'city' => 'Berlin',
            'country_iso2' => 'DE',
        ]);
    }

    public function test_update_does_not_require_all_fields(): void
    {
        $profile = $this->senderProfile(2);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn (Identifier $id) => $id->toInt() === 2),
                Mockery::on(fn (array $p) => $p['city'] === 'Köln' && $p['email'] === null),
            )
            ->andReturn($profile);

        $this->service->update(2, [
            'city' => '  Köln  ',
            'email' => '   ',
        ]);
    }

    public function test_delete_throws_when_profile_missing(): void
    {
        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->andReturnNull();

        $this->expectException(SenderProfileNotFoundException::class);

        $this->service->delete(99);
    }

    public function test_delete_executes_when_profile_exists(): void
    {
        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->andReturn($this->senderProfile(7));

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn (Identifier $id) => $id->toInt() === 7));

        $this->service->delete(7);
    }

    private function senderProfile(int $id): FulfillmentSenderProfile
    {
        return FulfillmentSenderProfile::hydrate(
            Identifier::fromInt($id),
            'main',
            'Ullrich',
            'Ullrich Sport',
            null,
            null,
            null,
            'Hauptstr.',
            '1',
            null,
            '12345',
            'Berlin',
            'DE',
        );
    }
}
