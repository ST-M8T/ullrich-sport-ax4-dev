<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment;

use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReceiverAddressMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_metadata_receiver_is_lazily_mapped_to_value_object(): void
    {
        $model = ShipmentOrderModel::create([
            'external_order_id' => 7001,
            'sender_code' => 'SND-LEGACY',
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'is_booked' => false,
            'processed_at' => CarbonImmutable::now(),
            'metadata' => [
                'receiver' => [
                    'companyName' => 'Acme GmbH',
                    'contactPerson' => 'Max Mustermann',
                    'streetName' => 'Hauptstraße',
                    'streetNumber' => '12a',
                    'postalCode' => '57462',
                    'city' => 'Olpe',
                    'countryIso2' => 'de',
                ],
            ],
        ]);

        // Simulate pre-migration row: blank out structured columns.
        DB::table('shipment_orders')->where('id', $model->id)->update([
            'receiver_street' => null,
            'receiver_postal_code' => null,
            'receiver_city_name' => null,
            'receiver_country_code' => null,
        ]);

        /** @var ShipmentOrderRepository $repo */
        $repo = $this->app->make(ShipmentOrderRepository::class);
        $order = $repo->getById(Identifier::fromInt((int) $model->id));

        self::assertNotNull($order);
        $receiver = $order->receiverAddress();
        self::assertNotNull($receiver, 'Lazy fallback to metadata[receiver] must populate the VO.');
        self::assertSame('Hauptstraße 12a', $receiver->street());
        self::assertSame('57462', $receiver->postalCode());
        self::assertSame('Olpe', $receiver->cityName());
        self::assertSame('DE', $receiver->countryCode());
        self::assertSame('Acme GmbH', $receiver->companyName());
        self::assertSame('Max Mustermann', $receiver->contactName());
    }

    public function test_structured_columns_take_precedence_over_legacy_metadata(): void
    {
        $model = ShipmentOrderModel::create([
            'external_order_id' => 7002,
            'sender_code' => 'SND-NEW',
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'is_booked' => false,
            'processed_at' => CarbonImmutable::now(),
            'receiver_street' => 'Bahnhofstraße 1',
            'receiver_postal_code' => '12345',
            'receiver_city_name' => 'Berlin',
            'receiver_country_code' => 'DE',
            'metadata' => [
                'receiver' => [
                    'streetName' => 'Andere Straße',
                    'streetNumber' => '99',
                    'postalCode' => '99999',
                    'city' => 'Woanders',
                    'countryIso2' => 'AT',
                ],
            ],
        ]);

        /** @var ShipmentOrderRepository $repo */
        $repo = $this->app->make(ShipmentOrderRepository::class);
        $order = $repo->getById(Identifier::fromInt((int) $model->id));

        self::assertNotNull($order);
        $receiver = $order->receiverAddress();
        self::assertNotNull($receiver);
        self::assertSame('Bahnhofstraße 1', $receiver->street());
        self::assertSame('Berlin', $receiver->cityName());
        self::assertSame('DE', $receiver->countryCode());
    }

    public function test_save_persists_receiver_address_columns(): void
    {
        $model = ShipmentOrderModel::create([
            'external_order_id' => 7003,
            'sender_code' => 'SND-SAVE',
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'is_booked' => false,
            'processed_at' => CarbonImmutable::now(),
        ]);

        /** @var ShipmentOrderRepository $repo */
        $repo = $this->app->make(ShipmentOrderRepository::class);

        $order = $repo->getById(Identifier::fromInt((int) $model->id));
        self::assertNotNull($order);

        $updated = $order->withReceiverAddress(
            \App\Domain\Fulfillment\Orders\ValueObjects\ShipmentReceiverAddress::create(
                street: 'Lagerweg 5',
                postalCode: '57462',
                cityName: 'Olpe',
                countryCode: 'DE',
                companyName: 'Ullrich Sport',
                email: 'ops@example.com',
            )
        );

        $repo->save($updated);

        $row = DB::table('shipment_orders')->where('id', $model->id)->first();
        self::assertNotNull($row);
        self::assertSame('Lagerweg 5', $row->receiver_street);
        self::assertSame('57462', $row->receiver_postal_code);
        self::assertSame('Olpe', $row->receiver_city_name);
        self::assertSame('DE', $row->receiver_country_code);
        self::assertSame('Ullrich Sport', $row->receiver_company_name);
        self::assertSame('ops@example.com', $row->receiver_email);
    }
}
