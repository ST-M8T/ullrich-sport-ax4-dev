<?php

namespace Tests\Feature\Console;

use App\Application\Dispatch\DispatchListService;
use App\Application\Monitoring\DomainEventService;
use App\Domain\Monitoring\Contracts\DomainEventRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\Support\Fakes\NullDomainEventRepository;
use Tests\TestCase;

final class DispatchCaptureScanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_scan_command_captures_scan(): void
    {
        $list = $this->createDispatchList(['status' => 'open']);
        $order = $this->createShipmentOrder();
        $user = $this->createInfrastructureUser();

        $this->app->forgetInstance(DomainEventService::class);
        $this->app->forgetInstance(DispatchListService::class);
        $this->app->instance(DomainEventRepository::class, new NullDomainEventRepository);

        $exitCode = Artisan::call('dispatch:scan', [
            'list' => (string) $list->getKey(),
            'barcode' => 'BARCODE-1',
            '--order-id' => (string) $order->getKey(),
            '--user-id' => (string) $user->getKey(),
            '--captured-at' => '2024-01-01T10:00:00+00:00',
            '--metadata' => json_encode(['lane' => 'A1'], JSON_THROW_ON_ERROR),
        ]);

        $output = Artisan::output();

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode, $output);
        $this->assertStringContainsString('Captured scan #', $output);

        $this->assertDatabaseHas('dispatch_scans', [
            'dispatch_list_id' => $list->getKey(),
            'barcode' => 'BARCODE-1',
            'shipment_order_id' => $order->getKey(),
        ]);

        $this->assertDatabaseHas('dispatch_scans', [
            'dispatch_list_id' => $list->getKey(),
            'metadata->lane' => 'A1',
        ]);
    }

    public function test_capture_scan_command_validates_datetime_input(): void
    {
        $this->createDispatchList(['status' => 'open']);

        $this->app->forgetInstance(DomainEventService::class);
        $this->app->forgetInstance(DispatchListService::class);
        $this->app->instance(DomainEventRepository::class, new NullDomainEventRepository);

        $this->artisan('dispatch:scan', [
            'list' => '1',
            'barcode' => 'ABC',
            '--captured-at' => 'not-a-date',
        ])
            ->expectsOutputToContain('Invalid captured-at timestamp')
            ->assertExitCode(1);

        $this->assertDatabaseCount('dispatch_scans', 0);
    }
}
