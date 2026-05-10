<?php

namespace Tests\Feature\Console;

use App\Application\Configuration\NotificationDispatchService;
use App\Domain\Configuration\Contracts\NotificationRepository;
use Mockery;
use Tests\TestCase;

final class NotificationsDispatchCommandTest extends TestCase
{
    public function test_notifications_dispatch_command_uses_limit_option(): void
    {
        $repository = Mockery::mock(NotificationRepository::class);
        $repository->shouldReceive('search')
            ->once()
            ->with(['status' => 'pending'], 25)
            ->andReturn([]);

        $service = new NotificationDispatchService($repository, []);
        $this->app->instance(NotificationDispatchService::class, $service);

        $this->artisan('notifications:dispatch', ['--limit' => 25])
            ->expectsOutputToContain('0 Benachrichtigungen verarbeitet, 0 gesendet.')
            ->assertExitCode(0);
    }
}
