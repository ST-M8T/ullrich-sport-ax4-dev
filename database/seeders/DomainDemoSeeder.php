<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Configuration\Eloquent\MailTemplateModel;
use App\Infrastructure\Persistence\Configuration\Eloquent\NotificationModel;
use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchListModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchMetricsModel;
use App\Infrastructure\Persistence\Dispatch\Eloquent\DispatchScanModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentAssemblyOptionModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentFreightProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentPackagingProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderRuleModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentVariationProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentEventModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderItemModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderShipmentModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentPackageModel;
use App\Infrastructure\Persistence\Identity\Eloquent\LoginAttemptModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel as InfrastructureUserModel;
use App\Infrastructure\Persistence\Monitoring\Eloquent\AuditLogModel;
use App\Infrastructure\Persistence\Monitoring\Eloquent\DomainEventModel;
use App\Infrastructure\Persistence\Monitoring\Eloquent\SystemJobModel;
use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingAlertModel;
use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingJobModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class DomainDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIdentity();
        ['packaging' => $packagingProfiles, 'freight' => $freightProfiles] = $this->seedFulfillmentMasterdata();
        $this->seedFulfillmentOperations($packagingProfiles, $freightProfiles);
        $this->seedDispatchModule();
        $this->seedTrackingModule();
        $this->seedConfigurationModule();
        $this->seedMonitoring();
    }

    /**
     * @return Collection<int,FulfillmentPackagingProfileModel>
     */
    /**
     * @return array{packaging:Collection<int,FulfillmentPackagingProfileModel>,freight:Collection<int,FulfillmentFreightProfileModel>}
     */
    private function seedFulfillmentMasterdata(): array
    {
        $packaging = FulfillmentPackagingProfileModel::factory()->count(6)->create();

        $assembly = FulfillmentAssemblyOptionModel::factory()
            ->count(4)
            ->state(function () use ($packaging) {
                return [
                    'assembly_packaging_id' => $packaging->random()->getKey(),
                ];
            })
            ->create();

        FulfillmentVariationProfileModel::factory()
            ->count(8)
            ->state(function () use ($packaging, $assembly) {
                $useAssembly = $assembly->isNotEmpty() && fake()->boolean(50);
                $defaultPackaging = $packaging->random();

                return [
                    'default_packaging_id' => $defaultPackaging->getKey(),
                    'default_state' => $useAssembly ? 'assembled' : 'kit',
                    'assembly_option_id' => $useAssembly ? $assembly->random()->getKey() : null,
                ];
            })
            ->create();

        $senders = FulfillmentSenderProfileModel::factory()->count(5)->create();

        FulfillmentSenderRuleModel::factory()
            ->count(8)
            ->state(function () use ($senders) {
                return [
                    'match_value' => fake()->randomElement([
                        fake()->postcode(),
                        strtoupper(fake()->countryCode()),
                        fake()->word(),
                    ]),
                    'target_sender_id' => $senders->random()->getKey(),
                ];
            })
            ->create();

        $freight = FulfillmentFreightProfileModel::factory()->count(3)->create();

        return [
            'packaging' => $packaging,
            'freight' => $freight,
        ];
    }

    /**
     * @param  Collection<int,FulfillmentPackagingProfileModel>  $packagingProfiles
     * @param  Collection<int,FulfillmentFreightProfileModel>  $freightProfiles
     */
    private function seedFulfillmentOperations(Collection $packagingProfiles, Collection $freightProfiles): void
    {
        $orders = ShipmentOrderModel::factory()
            ->count(6)
            ->has(ShipmentOrderItemModel::factory()->count(3), 'items')
            ->has(ShipmentPackageModel::factory()->count(2), 'packages')
            ->create();

        $shipments = ShipmentModel::factory()
            ->count(5)
            ->state(function () use ($freightProfiles) {
                return ['shipping_profile_id' => $freightProfiles->random()->getKey()];
            })
            ->has(ShipmentEventModel::factory()->count(3), 'events')
            ->create();

        $orders->each(function (ShipmentOrderModel $order) use ($shipments): void {
            ShipmentOrderShipmentModel::factory()
                ->state([
                    'shipment_order_id' => $order->getKey(),
                    'shipment_id' => $shipments->random()->getKey(),
                ])
                ->create();
        });
    }

    private function seedDispatchModule(): void
    {
        $lists = DispatchListModel::factory()->count(4)->create();

        DispatchMetricsModel::factory()
            ->count(4)
            ->state(function () use ($lists) {
                return ['dispatch_list_id' => $lists->random()->getKey()];
            })
            ->create();

        DispatchScanModel::factory()
            ->count(12)
            ->state(function () use ($lists) {
                return ['dispatch_list_id' => $lists->random()->getKey()];
            })
            ->create();
    }

    private function seedTrackingModule(): void
    {
        TrackingJobModel::factory()->count(8)->create();
        TrackingAlertModel::factory()->count(6)->create();
    }

    private function seedConfigurationModule(): void
    {
        SystemSettingModel::factory()->count(6)->create();

        $templates = MailTemplateModel::factory()->count(4)->create();

        NotificationModel::factory()
            ->count(6)
            ->state(function () use ($templates) {
                return [
                    'payload' => [
                        'recipient' => fake()->safeEmail(),
                        'template' => $templates->random()->template_key,
                        'data' => ['test' => true],
                    ],
                ];
            })
            ->create();
    }

    private function seedMonitoring(): void
    {
        SystemJobModel::factory()->count(6)->create();
        DomainEventModel::factory()->count(6)->create();
        AuditLogModel::factory()->count(8)->create();
    }

    private function seedIdentity(): void
    {
        InfrastructureUserModel::factory()->count(5)->create();
        LoginAttemptModel::factory()->count(8)->create();
    }
}
