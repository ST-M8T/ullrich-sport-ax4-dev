<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Fulfillment\Exports\ListShipmentExportFiles;
use App\Application\Fulfillment\Exports\ListShipmentExportJobs;
use App\Application\Fulfillment\Exports\ShipmentExportManager;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlLabelService;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlPayloadMapper;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlPriceQuoteService;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlShipmentBookingService;
use App\Application\Fulfillment\Masterdata\Queries\GetFulfillmentMasterdataCatalog;
use App\Application\Fulfillment\Orders\Commands\BookShipmentOrder;
use App\Application\Fulfillment\Orders\Commands\TransferShipmentOrderTracking;
use App\Application\Fulfillment\Orders\Events\ShipmentOrderBooked;
use App\Application\Fulfillment\Orders\Events\ShipmentOrderTrackingTransferred;
use App\Application\Fulfillment\Orders\Listeners\LogShipmentOrderBooked;
use App\Application\Fulfillment\Orders\Listeners\LogShipmentOrderTrackingTransferred;
use App\Application\Fulfillment\Orders\PlentyOrderSyncService;
use App\Application\Fulfillment\Orders\Queries\ListShipmentOrders;
use App\Application\Fulfillment\Orders\ShipmentOrderAdministrationService;
use App\Application\Fulfillment\Shipments\DhlTrackingSyncService;
use App\Application\Fulfillment\Shipments\Events\ShipmentEventRecorded;
use App\Application\Fulfillment\Shipments\Events\ShipmentManualSyncTriggered;
use App\Application\Fulfillment\Shipments\Listeners\RecordManualSync;
use App\Application\Fulfillment\Shipments\Listeners\RecordShipmentEvent;
use App\Application\Fulfillment\Shipments\ManualShipmentService;
use App\Application\Fulfillment\Shipments\Queries\GetShipmentDetail;
use App\Application\Fulfillment\Shipments\Queries\ListShipments;
use App\Application\Fulfillment\Shipments\ShipmentTrackingService;
use App\Domain\Fulfillment\Exports\ShipmentExportGenerator;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentAssemblyOptionRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderRuleRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentVariationProfileRepository;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository as FulfillmentShipmentRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\EloquentFulfillmentAssemblyOptionRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\EloquentFulfillmentFreightProfileRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\EloquentFulfillmentPackagingProfileRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\EloquentFulfillmentSenderProfileRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\EloquentFulfillmentSenderRuleRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\EloquentFulfillmentVariationProfileRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\EloquentShipmentOrderRepository;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Shipments\EloquentShipmentRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class FulfillmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FulfillmentPackagingProfileRepository::class, EloquentFulfillmentPackagingProfileRepository::class);
        $this->app->bind(FulfillmentAssemblyOptionRepository::class, EloquentFulfillmentAssemblyOptionRepository::class);
        $this->app->bind(FulfillmentVariationProfileRepository::class, EloquentFulfillmentVariationProfileRepository::class);
        $this->app->bind(FulfillmentSenderProfileRepository::class, EloquentFulfillmentSenderProfileRepository::class);
        $this->app->bind(FulfillmentSenderRuleRepository::class, EloquentFulfillmentSenderRuleRepository::class);
        $this->app->bind(FulfillmentFreightProfileRepository::class, EloquentFulfillmentFreightProfileRepository::class);
        $this->app->bind(ShipmentOrderRepository::class, EloquentShipmentOrderRepository::class);
        $this->app->bind(FulfillmentShipmentRepository::class, EloquentShipmentRepository::class);

        $this->app->singleton(ShipmentExportGenerator::class, function ($app) {
            return new ShipmentExportGenerator($app->make(ShipmentOrderRepository::class));
        });

        $this->app->singleton(GetFulfillmentMasterdataCatalog::class);
        $this->app->singleton(ListShipmentOrders::class);
        $this->app->singleton(ListShipments::class);
        $this->app->singleton(ShipmentTrackingService::class);
        $this->app->singleton(ManualShipmentService::class);
        $this->app->singleton(ShipmentOrderAdministrationService::class);
        $this->app->singleton(GetShipmentDetail::class);
        $this->app->singleton(PlentyOrderSyncService::class);
        $this->app->singleton(DhlTrackingSyncService::class);
        $this->app->singleton(BookShipmentOrder::class);
        $this->app->singleton(TransferShipmentOrderTracking::class);
        $this->app->singleton(ShipmentExportManager::class);
        $this->app->singleton(ListShipmentExportFiles::class);
        $this->app->singleton(ListShipmentExportJobs::class);

        $this->app->singleton(DhlPayloadMapper::class, function ($app) {
            $factor = (float) config('services.dhl_freight.volumetric_weight_factor', 250.0);

            return new DhlPayloadMapper($factor);
        });
        $this->app->singleton(DhlShipmentBookingService::class);
        $this->app->singleton(DhlLabelService::class);
        $this->app->singleton(DhlPriceQuoteService::class);
    }

    public function boot(): void
    {
        Event::listen(ShipmentOrderBooked::class, LogShipmentOrderBooked::class);
        Event::listen(ShipmentOrderTrackingTransferred::class, LogShipmentOrderTrackingTransferred::class);
        Event::listen(ShipmentEventRecorded::class, RecordShipmentEvent::class);
        Event::listen(ShipmentManualSyncTriggered::class, RecordManualSync::class);
    }
}
