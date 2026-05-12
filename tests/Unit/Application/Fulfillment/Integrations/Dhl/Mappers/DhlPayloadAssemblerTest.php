<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Integrations\Dhl\Mappers;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlServiceOptionCollection;
use App\Application\Fulfillment\Integrations\Dhl\Mappers\DhlPartyMapper;
use App\Application\Fulfillment\Integrations\Dhl\Mappers\DhlPayloadAssembler;
use App\Application\Fulfillment\Integrations\Dhl\Mappers\DhlPieceMapper;
use App\Application\Fulfillment\Integrations\Dhl\Mappers\DhlReferenceMapper;
use App\Application\Fulfillment\Integrations\Dhl\Mappers\Exceptions\DhlPayloadAssemblyException;
use App\Application\Fulfillment\Integrations\Dhl\Settings\DhlSettingsResolver;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentPackage;
use App\Domain\Fulfillment\Orders\ValueObjects\ShipmentReceiverAddress;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlAccountNumber;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlReferenceQualifier;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Covers DhlPartyMapper, DhlPieceMapper, DhlReferenceMapper and the orchestrating
 * DhlPayloadAssembler. The four mappers form ONE logical seam (DDD strict-DRY): a
 * single test class keeps the contract-level invariants together.
 */
final class DhlPayloadAssemblerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_party_mapper_consignor_truncates_and_attaches_account_number(): void
    {
        $sender = $this->makeSender();
        $account = new DhlAccountNumber('ACC-001');

        $party = DhlPartyMapper::consignorFromSenderProfile($sender, $account);

        self::assertSame('Consignor', $party->type->value);
        self::assertSame('Ullrich Sport GmbH', $party->name);
        self::assertSame('ACC-001', $party->id?->value);
        self::assertSame('contact@ullrich-sport.de', $party->email);
        self::assertSame('Hauptstr. 1', $party->address->street);
    }

    public function test_party_mapper_consignee_picks_company_then_contact_then_dash(): void
    {
        $order = $this->makeOrder(receiverCompany: 'Acme Corp', receiverContact: null);
        $consignee = DhlPartyMapper::consigneeFromOrder($order);
        self::assertSame('Acme Corp', $consignee->name);

        $order2 = $this->makeOrder(receiverCompany: null, receiverContact: 'Jane Doe');
        $consignee2 = DhlPartyMapper::consigneeFromOrder($order2);
        self::assertSame('Jane Doe', $consignee2->name);
    }

    public function test_piece_mapper_converts_mm_to_cm_and_uses_default_package_type(): void
    {
        $package = ShipmentPackage::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(10),
            null,
            'PKG-REF-001',
            2,
            12.5,
            1200, // mm
            800,
            500,
            1,
        );

        $piece = DhlPieceMapper::fromShipmentPackage($package, new DhlPackageType('PLT'));

        self::assertSame(2, $piece->numberOfPieces);
        self::assertSame('PLT', $piece->packageType->code);
        self::assertEqualsWithDelta(12.5, $piece->weight, 0.0001);
        self::assertEqualsWithDelta(120.0, $piece->length, 0.0001);
        self::assertEqualsWithDelta(80.0, $piece->width, 0.0001);
        self::assertEqualsWithDelta(50.0, $piece->height, 0.0001);
        self::assertSame('PKG-REF-001', $piece->marksAndNumbers);
    }

    public function test_piece_mapper_fails_when_weight_missing(): void
    {
        $package = ShipmentPackage::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(10),
            null,
            null,
            1,
            null,
            null,
            null,
            null,
            1,
        );

        $this->expectException(DhlPayloadAssemblyException::class);
        DhlPieceMapper::fromShipmentPackage($package, new DhlPackageType('PLT'));
    }

    public function test_reference_mapper_emits_cnr_always_and_cnz_only_when_customer_present(): void
    {
        $orderWith = $this->makeOrder(customerNumber: 4711);
        $refs = DhlReferenceMapper::fromOrder($orderWith);
        self::assertCount(2, $refs);
        self::assertSame(DhlReferenceQualifier::CNR, $refs[0]->qualifier);
        self::assertSame('1001', $refs[0]->value);
        self::assertSame(DhlReferenceQualifier::CNZ, $refs[1]->qualifier);
        self::assertSame('4711', $refs[1]->value);

        $orderWithout = $this->makeOrder(customerNumber: null);
        $refsOnly = DhlReferenceMapper::fromOrder($orderWithout);
        self::assertCount(1, $refsOnly);
        self::assertSame(DhlReferenceQualifier::CNR, $refsOnly[0]->qualifier);
    }

    public function test_assembler_builds_v2_booking_payload_with_totals_and_optionals(): void
    {
        $resolver = $this->makeResolverWithDefaultAccount('SYSACC');

        $payload = DhlPayloadAssembler::buildBookingPayload(
            $this->makeOrder(),
            $this->makeSender(),
            $this->makeOptions(withPickup: '2026-05-13', withServices: ['PR']),
            $resolver,
        );

        self::assertSame('v2', $payload['_schema']);
        self::assertSame('V01', $payload['productCode']);
        self::assertSame('DAP', $payload['payerCode']);
        self::assertSame(1, $payload['totalNumberOfPieces']);
        self::assertEqualsWithDelta(10.0, $payload['totalWeight'], 0.0001);
        self::assertCount(2, $payload['parties']);
        self::assertSame('Consignor', $payload['parties'][0]['type']);
        self::assertSame('Consignee', $payload['parties'][1]['type']);
        self::assertSame('SYSACC', $payload['parties'][0]['id']);
        self::assertSame('2026-05-13', $payload['pickupDate']);
        self::assertArrayHasKey('additionalServices', $payload);
        self::assertCount(1, $payload['references']);
    }

    public function test_assembler_uses_form_pieces_override_instead_of_order_packages(): void
    {
        $resolver = $this->makeResolverWithDefaultAccount('SYSACC');

        // Order has ONE package (10 kg, 100x50x50 cm via 1000mm/500/500).
        // Form-Override liefert ZWEI Pieces (3+1) mit anderen Werten — Override muss gewinnen.
        $options = DhlBookingOptions::fromArray([
            'product_code' => 'V01',
            'payer_code' => 'DAP',
            'default_package_type' => 'PLT',
            'pieces' => [
                [
                    'number_of_pieces' => 3,
                    'package_type' => 'COLI',
                    'weight' => 4.5,
                    'width' => 80,
                    'height' => 60,
                    'length' => 120,
                    'marks_and_numbers' => 'PALLET-A',
                ],
                [
                    'number_of_pieces' => 1,
                    // package_type weggelassen → Fallback auf default_package_type (PLT)
                    'weight' => 12.0,
                ],
            ],
        ]);

        $payload = DhlPayloadAssembler::buildBookingPayload(
            $this->makeOrder(),
            $this->makeSender(),
            $options,
            $resolver,
        );

        self::assertCount(2, $payload['pieces'], 'Override pieces must be used, not order.packages()');
        self::assertSame(3, $payload['pieces'][0]['numberOfPieces']);
        self::assertSame('COLI', $payload['pieces'][0]['packageType']);
        self::assertEqualsWithDelta(4.5, $payload['pieces'][0]['weight'], 0.0001);
        self::assertSame(80.0, $payload['pieces'][0]['width']);
        self::assertSame('PALLET-A', $payload['pieces'][0]['marksAndNumbers']);

        self::assertSame(1, $payload['pieces'][1]['numberOfPieces']);
        self::assertSame('PLT', $payload['pieces'][1]['packageType']);
        self::assertEqualsWithDelta(12.0, $payload['pieces'][1]['weight'], 0.0001);

        // 3*4.5 + 1*12.0 = 25.5
        self::assertSame(4, $payload['totalNumberOfPieces']);
        self::assertEqualsWithDelta(25.5, $payload['totalWeight'], 0.0001);
    }

    public function test_assembler_falls_back_to_order_packages_when_no_override(): void
    {
        $resolver = $this->makeResolverWithDefaultAccount('SYSACC');

        $options = DhlBookingOptions::fromArray([
            'product_code' => 'V01',
            'payer_code' => 'DAP',
            'default_package_type' => 'PLT',
            // kein 'pieces' → Backward-Compat
        ]);

        $payload = DhlPayloadAssembler::buildBookingPayload(
            $this->makeOrder(),
            $this->makeSender(),
            $options,
            $resolver,
        );

        self::assertCount(1, $payload['pieces']);
        self::assertSame(1, $payload['totalNumberOfPieces']);
        self::assertEqualsWithDelta(10.0, $payload['totalWeight'], 0.0001);
    }

    public function test_assembler_omits_optionals_when_not_set(): void
    {
        $resolver = $this->makeResolverWithDefaultAccount('SYSACC');
        $payload = DhlPayloadAssembler::buildBookingPayload(
            $this->makeOrder(),
            $this->makeSender(),
            $this->makeOptions(),
            $resolver,
        );

        self::assertArrayNotHasKey('pickupDate', $payload);
        self::assertArrayNotHasKey('additionalServices', $payload);
    }

    public function test_assembler_throws_when_product_code_missing(): void
    {
        $resolver = $this->makeResolverWithDefaultAccount('SYSACC');
        $options = new DhlBookingOptions(
            null,
            DhlServiceOptionCollection::fromArray([]),
            null,
            productCode: null,
            payerCode: DhlPayerCode::DAP,
            defaultPackageType: new DhlPackageType('PLT'),
        );

        $this->expectException(DhlPayloadAssemblyException::class);
        DhlPayloadAssembler::buildBookingPayload(
            $this->makeOrder(),
            $this->makeSender(),
            $options,
            $resolver,
        );
    }

    public function test_assembler_throws_when_payer_code_missing(): void
    {
        $resolver = $this->makeResolverWithDefaultAccount('SYSACC');
        $options = new DhlBookingOptions(
            null,
            DhlServiceOptionCollection::fromArray([]),
            null,
            productCode: new DhlProductCode('V01'),
            payerCode: null,
            defaultPackageType: new DhlPackageType('PLT'),
        );

        $this->expectException(DhlPayloadAssemblyException::class);
        DhlPayloadAssembler::buildBookingPayload(
            $this->makeOrder(),
            $this->makeSender(),
            $options,
            $resolver,
        );
    }

    public function test_freight_profile_account_number_overrides_system_default(): void
    {
        // t30: Profile.account_number > DhlConfiguration.defaultAccountNumber.
        $configRepo = Mockery::mock(DhlConfigurationRepository::class);
        $profileRepo = Mockery::mock(FulfillmentFreightProfileRepository::class);

        $config = DhlConfiguration::create(
            authBaseUrl: 'https://auth.example.com',
            authClientId: 'cid',
            authClientSecret: 'csec',
            freightBaseUrl: 'https://api.example.com',
            freightApiKey: 'k',
            freightApiSecret: 's',
        );
        $config->setDefaultAccountNumber('SYS-DEFAULT');
        $configRepo->shouldReceive('load')->andReturn($config);

        $profile = FulfillmentFreightProfile::hydrate(
            shippingProfileId: Identifier::fromInt(99),
            label: 'override',
            accountNumber: 'PROFILE-ACC-99',
        );
        $profileRepo->shouldReceive('getById')
            ->with(Mockery::on(static fn ($id): bool => $id instanceof Identifier && $id->toInt() === 99))
            ->andReturn($profile);

        $resolver = new DhlSettingsResolver($configRepo, $profileRepo);

        $payload = DhlPayloadAssembler::buildBookingPayload(
            $this->makeOrder(),
            $this->makeSender(),
            $this->makeOptions(),
            $resolver,
            99,
        );

        self::assertSame('PROFILE-ACC-99', $payload['parties'][0]['id']);
    }

    public function test_party_mapper_truncates_overlong_company_and_contact_to_spec_max(): void
    {
        // Spec: name max 35, contactName max 35, phone max 22, email max 60.
        // Mapper MUST truncate BEFORE handing data to the VO (which fails fast).
        $longName = str_repeat('A', 80);
        $longContact = str_repeat('B', 80);
        $longPhone = str_repeat('1', 40);
        $longEmail = str_repeat('e', 50).'@example.com'; // 62 chars total
        $sender = FulfillmentSenderProfile::hydrate(
            id: Identifier::fromInt(1),
            senderCode: 'main',
            displayName: 'Main',
            companyName: $longName,
            contactPerson: $longContact,
            email: $longEmail,
            phone: $longPhone,
            streetName: 'Hauptstr.',
            streetNumber: '1',
            addressAddition: null,
            postalCode: '10115',
            city: 'Berlin',
            countryIso2: 'de',
        );

        $party = DhlPartyMapper::consignorFromSenderProfile($sender, new DhlAccountNumber('ACC-001'));

        self::assertSame(35, mb_strlen($party->name));
        self::assertSame(35, mb_strlen($party->contactName ?? ''));
        self::assertSame(22, mb_strlen($party->phone ?? ''));
        self::assertSame(60, mb_strlen($party->email ?? ''));
    }

    public function test_assembler_aggregates_multiple_packages_into_pieces_and_totals(): void
    {
        $resolver = $this->makeResolverWithDefaultAccount('SYSACC');

        $package1 = ShipmentPackage::hydrate(
            Identifier::fromInt(1), Identifier::fromInt(100), null, 'P1',
            2, 5.0, 1000, 500, 500, 1,
        );
        $package2 = ShipmentPackage::hydrate(
            Identifier::fromInt(2), Identifier::fromInt(100), null, 'P2',
            3, 7.5, 1200, 600, 400, 2,
        );

        $receiver = ShipmentReceiverAddress::create(
            street: 'Werkstr. 5', postalCode: '12345', cityName: 'Hamburg',
            countryCode: 'DE', companyName: 'Acme Corp', contactName: null,
        );
        $order = ShipmentOrder::hydrate(
            id: Identifier::fromInt(100), externalOrderId: 1001, customerNumber: null,
            plentyOrderId: null, orderType: null, senderProfileId: Identifier::fromInt(1),
            senderCode: 'main', contactEmail: null, contactPhone: null,
            destinationCountry: 'DE', currency: 'EUR', totalAmount: null, processedAt: null,
            isBooked: false, bookedAt: null, bookedBy: null, shippedAt: null,
            lastExportFilename: null, items: [], packages: [$package1, $package2],
            trackingNumbers: [], metadata: [],
            createdAt: new DateTimeImmutable, updatedAt: new DateTimeImmutable,
            receiverAddress: $receiver,
        );

        $payload = DhlPayloadAssembler::buildBookingPayload(
            $order, $this->makeSender(), $this->makeOptions(), $resolver,
        );

        self::assertCount(2, $payload['pieces']);
        self::assertSame(5, $payload['totalNumberOfPieces']); // 2 + 3
        self::assertEqualsWithDelta(2 * 5.0 + 3 * 7.5, $payload['totalWeight'], 0.0001);
    }

    public function test_quote_payload_omits_payer_code_when_not_set(): void
    {
        $resolver = $this->makeResolverWithDefaultAccount('SYSACC');
        $options = new DhlBookingOptions(
            null,
            DhlServiceOptionCollection::fromArray([]),
            null,
            productCode: new DhlProductCode('V01'),
            payerCode: null,
            defaultPackageType: new DhlPackageType('PLT'),
        );

        $payload = DhlPayloadAssembler::buildPriceQuotePayload(
            $this->makeOrder(),
            $this->makeSender(),
            $options,
            $resolver,
        );

        self::assertSame('v2', $payload['_schema']);
        self::assertSame('V01', $payload['productCode']);
        self::assertArrayNotHasKey('payerCode', $payload);
        self::assertArrayNotHasKey('references', $payload);
    }

    private function makeSender(): FulfillmentSenderProfile
    {
        return FulfillmentSenderProfile::hydrate(
            id: Identifier::fromInt(1),
            senderCode: 'main',
            displayName: 'Main',
            companyName: 'Ullrich Sport GmbH',
            contactPerson: 'Max Mustermann',
            email: 'contact@ullrich-sport.de',
            phone: '+49 30 1234567',
            streetName: 'Hauptstr.',
            streetNumber: '1',
            addressAddition: null,
            postalCode: '10115',
            city: 'Berlin',
            countryIso2: 'de',
        );
    }

    private function makeOrder(
        ?string $receiverCompany = 'Acme Corp',
        ?string $receiverContact = 'Jane Doe',
        ?int $customerNumber = null,
    ): ShipmentOrder {
        $package = ShipmentPackage::hydrate(
            Identifier::fromInt(1),
            Identifier::fromInt(100),
            null,
            null,
            1,
            10.0,
            1000,
            500,
            500,
            1,
        );

        $receiver = ShipmentReceiverAddress::create(
            street: 'Werkstr. 5',
            postalCode: '12345',
            cityName: 'Hamburg',
            countryCode: 'DE',
            companyName: $receiverCompany,
            contactName: $receiverContact,
        );

        return ShipmentOrder::hydrate(
            id: Identifier::fromInt(100),
            externalOrderId: 1001,
            customerNumber: $customerNumber,
            plentyOrderId: null,
            orderType: null,
            senderProfileId: Identifier::fromInt(1),
            senderCode: 'main',
            contactEmail: null,
            contactPhone: null,
            destinationCountry: 'DE',
            currency: 'EUR',
            totalAmount: null,
            processedAt: null,
            isBooked: false,
            bookedAt: null,
            bookedBy: null,
            shippedAt: null,
            lastExportFilename: null,
            items: [],
            packages: [$package],
            trackingNumbers: [],
            metadata: [],
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            receiverAddress: $receiver,
        );
    }

    private function makeOptions(
        ?string $withPickup = null,
        array $withServices = [],
    ): DhlBookingOptions {
        return new DhlBookingOptions(
            null,
            DhlServiceOptionCollection::fromArray($withServices),
            $withPickup,
            productCode: new DhlProductCode('V01'),
            payerCode: DhlPayerCode::DAP,
            defaultPackageType: new DhlPackageType('PLT'),
        );
    }

    private function makeResolverWithDefaultAccount(string $account): DhlSettingsResolver
    {
        $configRepo = Mockery::mock(DhlConfigurationRepository::class);
        $profileRepo = Mockery::mock(FulfillmentFreightProfileRepository::class);

        $config = DhlConfiguration::create(
            authBaseUrl: 'https://auth.example.com',
            authClientId: 'cid',
            authClientSecret: 'csec',
            freightBaseUrl: 'https://api.example.com',
            freightApiKey: 'k',
            freightApiSecret: 's',
        );
        $config->setDefaultAccountNumber($account);

        $configRepo->shouldReceive('load')->andReturn($config);

        return new DhlSettingsResolver($configRepo, $profileRepo);
    }
}
