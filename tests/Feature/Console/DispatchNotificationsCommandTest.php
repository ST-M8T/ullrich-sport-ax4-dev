<?php

namespace Tests\Feature\Console;

use App\Infrastructure\Persistence\Configuration\Eloquent\MailTemplateModel;
use App\Infrastructure\Persistence\Configuration\Eloquent\NotificationModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class DispatchNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_notifications_are_dispatched(): void
    {
        Mail::fake();

        MailTemplateModel::create([
            'template_key' => 'test.welcome',
            'description' => 'Test Template',
            'subject' => 'Welcome',
            'body_text' => 'Hello',
            'is_active' => true,
        ]);

        NotificationModel::create([
            'notification_type' => 'user.welcome',
            'channel' => 'mail',
            'payload' => [
                'template' => 'test.welcome',
                'to' => 'user@example.com',
            ],
            'status' => 'pending',
        ]);

        $this->artisan('notifications:dispatch', ['--limit' => 10])
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications_queue', [
            'notification_type' => 'user.welcome',
            'status' => 'sent',
        ]);
    }
}
