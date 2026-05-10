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

final class DispatchCloseListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_list_command_requires_user_option(): void
    {
        $list = $this->createDispatchList();

        $this->app->forgetInstance(DomainEventService::class);
        $this->app->forgetInstance(DispatchListService::class);
        $this->app->instance(DomainEventRepository::class, new NullDomainEventRepository);

        $this->artisan('dispatch:close', ['list' => (string) $list->getKey()])
            ->expectsOutput('user-id option is required.')
            ->assertExitCode(1);
    }

    public function test_close_list_command_closes_list(): void
    {
        $list = $this->createDispatchList(['status' => 'open']);

        $this->app->forgetInstance(DomainEventService::class);
        $this->app->forgetInstance(DispatchListService::class);
        $this->app->instance(DomainEventRepository::class, new NullDomainEventRepository);

        $user = $this->createInfrastructureUser();

        $exitCode = Artisan::call('dispatch:close', [
            'list' => (string) $list->getKey(),
            '--user-id' => (string) $user->getKey(),
            '--export-file' => 'export.csv',
        ]);

        $output = Artisan::output();

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode, $output);
        $this->assertStringContainsString('Dispatch list #'.$list->getKey().' closed and exported as export.csv.', $output);

        $this->assertDatabaseHas('dispatch_lists', [
            'id' => $list->getKey(),
            'status' => 'exported',
            'closed_by_user_id' => $user->getKey(),
            'export_filename' => 'export.csv',
        ]);
    }
}
