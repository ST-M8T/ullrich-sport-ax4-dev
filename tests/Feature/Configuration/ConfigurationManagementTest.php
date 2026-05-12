<?php

namespace Tests\Feature\Configuration;

use App\Application\Configuration\MailTemplateService;
use App\Application\Configuration\SystemSettingService;
use App\Infrastructure\Persistence\Configuration\Eloquent\NotificationModel;
use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_system_setting_group_redirects_back_to_saved_group(): void
    {
        $response = $this->post(route('configuration-settings.group-update', ['group' => 'dhl']), [
            'dhl_auth_base_url' => 'https://api-sandbox.dhl.com',
            'dhl_auth_username' => 'client-id',
            'dhl_auth_path' => '/auth/v1/token',
            'dhl_auth_token_cache_ttl' => '0',
            'dhl_base_url' => 'https://api-test.dhl.com/tracking',
            'dhl_api_key' => 'tracking-key',
            'dhl_timeout' => '10',
            'dhl_connect_timeout' => '5',
            'dhl_verify_ssl' => '1',
            'dhl_push_base_url' => 'https://api-test.dhl.com/tracking/push/v1',
            'dhl_push_api_key' => 'push-key',
            'dhl_push_api_key_header' => 'DHL-API-Key',
            'tracking_api_key' => 'backend-key',
            'tracking_default_service' => 'freight',
            'tracking_origin_cc' => 'DE',
            'tracking_requester_cc' => 'DE',
        ]);

        $response->assertRedirect(route('configuration-settings', [
            'tab' => 'settings',
            'settings_group' => 'dhl',
        ]));
    }

    public function test_settings_page_shows_flash_once_and_exposes_dhl_navigation(): void
    {
        $response = $this
            ->withSession(['success' => 'DHL Integration gespeichert.'])
            ->get(route('configuration-settings'));

        $response->assertOk();
        $response->assertSee('sidebar-tabs__link-text">DHL Integration', false);
        $this->assertSame(1, substr_count($response->getContent(), 'DHL Integration gespeichert.'));
    }

    public function test_integration_form_test_action_uses_post_without_put_spoofing(): void
    {
        // DHL Freight Konfiguration ist nach Versand → DHL Freight konsolidiert.
        // Die Integrations-Show-Route leitet daher um (siehe IntegrationController).
        $response = $this->get(route('configuration-integrations.show', [
            'integrationKey' => 'dhl_freight',
        ]));

        $response->assertRedirect(route('admin.settings.dhl-freight.index'));
    }

    public function test_dhl_freight_connection_test_accepts_post_from_configuration_form(): void
    {
        Http::fake([
            'https://api-sandbox.dhl.com/freight' => Http::response('', 200),
        ]);

        $response = $this->post(route('configuration-integrations.test', [
            'integrationKey' => 'dhl_freight',
        ]), [
            'configuration' => [
                'dhl_freight_base_url' => 'https://api-sandbox.dhl.com/freight',
                'dhl_freight_api_key' => 'test-api-key',
                'dhl_freight_api_secret' => 'test-api-secret',
                'dhl_freight_auth' => 'bearer',
                'dhl_freight_timeout' => '10',
                'dhl_freight_connect_timeout' => '5',
                'dhl_freight_verify_ssl' => '1',
            ],
        ]);

        // DHL Freight Konfiguration ist nach Versand → DHL Freight konsolidiert
        // (siehe IntegrationController::REDIRECTED_TO_DHL_FREIGHT_SETTINGS).
        // Der Verbindungstest läuft jetzt unter admin.settings.dhl-freight.index.
        $response->assertRedirect(route('admin.settings.dhl-freight.index'));
        $response->assertSessionHas('info');
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
