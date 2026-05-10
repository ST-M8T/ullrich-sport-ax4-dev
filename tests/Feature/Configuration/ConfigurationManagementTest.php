<?php

namespace Tests\Feature\Configuration;

use App\Application\Configuration\MailTemplateService;
use App\Application\Configuration\SystemSettingService;
use App\Infrastructure\Persistence\Configuration\Eloquent\NotificationModel;
use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class ConfigurationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInWithRole('admin');
    }

    public function test_system_setting_can_be_created(): void
    {
        $response = $this->post(route('configuration-settings.store'), [
            'setting_key' => 'app.support_email',
            'value_type' => 'string',
            'setting_value' => 'support@example.test',
        ]);

        $response->assertRedirect(route('configuration-settings'));

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'app.support_email',
            'setting_value' => 'support@example.test',
            'value_type' => 'string',
        ]);
    }

    public function test_mail_template_can_be_created_via_form(): void
    {
        $response = $this->post(route('configuration-mail-templates.store'), [
            'template_key' => 'order_confirmed',
            'description' => 'Bestellbestätigung',
            'subject' => 'Ihre Bestellung',
            'body_html' => '<p>Hallo</p>',
            'body_text' => 'Hallo',
            'is_active' => '1',
        ]);

        // Mail-Templates werden inzwischen via Settings-Tab "settings_group=mail" angezeigt.
        $response->assertRedirect(route('configuration-settings', [
            'tab' => 'settings',
            'settings_group' => 'mail',
        ]));

        $this->assertDatabaseHas('mail_templates', [
            'template_key' => 'order_confirmed',
            'subject' => 'Ihre Bestellung',
            'is_active' => 1,
        ]);
    }

    public function test_notification_can_be_dispatched_from_admin(): void
    {
        Mail::fake();

        /** @var MailTemplateService $templates */
        $templates = app(MailTemplateService::class);
        $template = $templates->upsert(
            null,
            'dispatch_template',
            'Dispatch Test',
            '<p>Dispatch</p>',
            'Dispatch',
            true,
        );

        $response = $this->post(route('configuration-notifications.store'), [
            'notification_type' => 'dispatch.test',
            'template_key' => $template->templateKey(),
            'recipient' => 'notify@example.test',
            'payload' => json_encode(['extra' => 'data']),
        ]);

        // Notifications-Verwaltung läuft inzwischen über den Settings-Tab "verwaltung > notifications".
        $response->assertRedirect(route('configuration-settings', [
            'tab' => 'verwaltung',
            'verwaltung_tab' => 'notifications',
        ]));

        $this->post(route('configuration-notifications.dispatch'), ['limit' => 10])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications_queue', [
            'notification_type' => 'dispatch.test',
            'status' => 'sent',
        ]);

        $message = NotificationModel::query()->where('notification_type', 'dispatch.test')->first();
        $this->assertNotNull($message);
        $this->assertNotNull($message->sent_at);
    }

    public function test_notification_channel_settings_can_be_saved(): void
    {
        $response = $this->post(route('configuration-notifications.settings'), [
            'channels' => [
                'mail' => [
                    'enabled' => '1',
                    'from_email' => 'dispatch@example.test',
                    'from_name' => 'Dispatch Team',
                ],
                'slack' => [
                    'enabled' => '1',
                    'webhook_url' => 'https://hooks.slack.com/services/T000/B000/XXXX',
                    'channel' => '#alerts',
                ],
                'sms' => [
                    'enabled' => '1',
                    'sender' => 'OpsAlert',
                ],
            ],
        ]);

        // Notification-Channel-Settings landen im Settings-Tab "settings > notifications".
        $response->assertRedirect(route('configuration-settings', [
            'tab' => 'settings',
            'settings_group' => 'notifications',
        ]));

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'notifications.mail.enabled',
            'setting_value' => '1',
            'value_type' => 'bool',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'mail_from_email',
            'setting_value' => 'dispatch@example.test',
        ]);

        $slackSetting = SystemSettingModel::query()->where('setting_key', 'notifications.slack.webhook_url')->first();
        $this->assertNotNull($slackSetting);
        $this->assertSame('secret', $slackSetting->value_type);
        $this->assertNotSame('https://hooks.slack.com/services/T000/B000/XXXX', $slackSetting->setting_value);

        /** @var SystemSettingService $settings */
        $settings = app(SystemSettingService::class);
        $this->assertSame(
            'https://hooks.slack.com/services/T000/B000/XXXX',
            $settings->get('notifications.slack.webhook_url')
        );

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'notifications.sms.sender',
            'setting_value' => 'OpsAlert',
        ]);
    }

    public function test_notification_channel_settings_validation(): void
    {
        $response = $this->from(route('configuration-notifications'))
            ->post(route('configuration-notifications.settings'), [
                'channels' => [
                    'mail' => [],
                    'slack' => [
                        'webhook_url' => 'invalid-url',
                    ],
                    'sms' => [
                        'enabled' => 'maybe',
                    ],
                ],
            ]);

        $response->assertRedirect(route('configuration-notifications'));
        $response->assertSessionHasErrors([
            'channels.slack.webhook_url',
            'channels.sms.enabled',
        ]);
    }
}
