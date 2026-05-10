<?php

namespace Tests\Support;

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
use Database\Seeders\DomainDemoSeeder;
use Illuminate\Support\Collection;

/**
 * Helper methods for arranging domain data in tests.
 */
trait CreatesDomainData
{
    protected function seedDemoData(): void
    {
        $this->seed(DomainDemoSeeder::class);
    }

    protected function createPackagingProfile(array $attributes = []): FulfillmentPackagingProfileModel
    {
        return FulfillmentPackagingProfileModel::factory()->create($attributes);
    }

    protected function createAssemblyOption(array $attributes = []): FulfillmentAssemblyOptionModel
    {
        return FulfillmentAssemblyOptionModel::factory()->create($attributes);
    }

    protected function createVariationProfile(array $attributes = []): FulfillmentVariationProfileModel
    {
        return FulfillmentVariationProfileModel::factory()->create($attributes);
    }

    protected function createSenderProfile(array $attributes = []): FulfillmentSenderProfileModel
    {
        return FulfillmentSenderProfileModel::factory()->create($attributes);
    }

    protected function createSenderRule(array $attributes = []): FulfillmentSenderRuleModel
    {
        $sender = $attributes['target_sender_id'] ?? null;

        return FulfillmentSenderRuleModel::factory()
            ->state($attributes)
            ->state(function () use ($sender) {
                return $sender ? [] : ['target_sender_id' => FulfillmentSenderProfileModel::factory()->create()->getKey()];
            })
            ->create();
    }

    protected function createShipmentOrder(array $attributes = [], int $items = 0, int $packages = 0): ShipmentOrderModel
    {
        $order = ShipmentOrderModel::factory()->create($attributes);

        if ($items > 0) {
            ShipmentOrderItemModel::factory()
                ->count($items)
                ->state(['shipment_order_id' => $order->getKey()])
                ->create();
        }

        if ($packages > 0) {
            ShipmentPackageModel::factory()
                ->count($packages)
                ->state(['shipment_order_id' => $order->getKey()])
                ->create();
        }

        return $order->fresh(['items', 'packages']);
    }

    protected function createShipment(array $attributes = [], int $events = 0): ShipmentModel
    {
        if (! array_key_exists('shipping_profile_id', $attributes) || $attributes['shipping_profile_id'] === null) {
            $attributes['shipping_profile_id'] = FulfillmentFreightProfileModel::factory()->create()->getKey();
        }

        $shipment = ShipmentModel::factory()->create($attributes);

        if ($events > 0) {
            ShipmentEventModel::factory()
                ->count($events)
                ->state(['shipment_id' => $shipment->getKey()])
                ->create();
        }

        return $shipment->fresh('events');
    }

    protected function linkShipmentToOrder(ShipmentOrderModel $order, ShipmentModel $shipment): ShipmentOrderShipmentModel
    {
        return ShipmentOrderShipmentModel::factory()->create([
            'shipment_order_id' => $order->getKey(),
            'shipment_id' => $shipment->getKey(),
        ]);
    }

    protected function createDispatchList(array $attributes = [], int $scanCount = 0): DispatchListModel
    {
        $list = DispatchListModel::factory()->create($attributes);

        DispatchMetricsModel::factory()->create(['dispatch_list_id' => $list->getKey()]);

        if ($scanCount > 0) {
            DispatchScanModel::factory()
                ->count($scanCount)
                ->state(['dispatch_list_id' => $list->getKey()])
                ->create();
        }

        return $list->fresh(['metrics', 'scans']);
    }

    protected function createTrackingJob(array $attributes = []): TrackingJobModel
    {
        return TrackingJobModel::factory()->create($attributes);
    }

    protected function createTrackingAlert(array $attributes = []): TrackingAlertModel
    {
        return TrackingAlertModel::factory()->create($attributes);
    }

    protected function createSystemSetting(array $attributes = []): SystemSettingModel
    {
        return SystemSettingModel::factory()->create($attributes);
    }

    protected function createMailTemplate(array $attributes = []): MailTemplateModel
    {
        return MailTemplateModel::factory()->create($attributes);
    }

    protected function createNotification(array $attributes = []): NotificationModel
    {
        return NotificationModel::factory()->create($attributes);
    }

    protected function createSystemJob(array $attributes = []): SystemJobModel
    {
        return SystemJobModel::factory()->create($attributes);
    }

    protected function createDomainEvent(array $attributes = []): DomainEventModel
    {
        return DomainEventModel::factory()->create($attributes);
    }

    protected function createAuditLog(array $attributes = []): AuditLogModel
    {
        return AuditLogModel::factory()->create($attributes);
    }

    protected function createInfrastructureUser(array $attributes = []): InfrastructureUserModel
    {
        return InfrastructureUserModel::factory()->create($attributes);
    }

    protected function actingAsAdmin(array $attributes = []): InfrastructureUserModel
    {
        $user = InfrastructureUserModel::factory()->create(array_merge([
            'role' => 'admin',
            'password_hash' => bcrypt('password'),
            'must_change_password' => false,
            'disabled' => false,
        ], $attributes));

        $this->actingAs($user);

        return $user;
    }

    protected function createLoginAttempt(array $attributes = []): LoginAttemptModel
    {
        return LoginAttemptModel::factory()->create($attributes);
    }

    protected function collectShipmentOrders(): Collection
    {
        return ShipmentOrderModel::query()->with(['items', 'packages'])->get();
    }
}
