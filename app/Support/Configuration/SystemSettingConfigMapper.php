<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Application\Configuration\SystemSettingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SystemSettingConfigMapper
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function apply(): void
    {
        if (! $this->systemSettingsTableExists()) {
            return;
        }

        $this->applyMailConfig();
        $this->applyDhlTracking();
        $this->applyDhlAuth();
        $this->applyDhlFreight();
        $this->applyDhlPush();
    }

    /**
     * Robuster Existenz-Check für die `system_settings`-Tabelle.
     *
     * Beim App-Boot in Umgebungen ohne erreichbare DB (PhpStan-Bootstrap,
     * `php artisan config:cache` mit fehlender DB, frischer Container vor
     * Migration) wirft `Schema::hasTable` eine QueryException. Engineering-
     * Handbuch §16: keine stillen Catches — daher wird die Ursache geloggt
     * und der Mapper überspringt die Anwendung der Settings.
     */
    private function systemSettingsTableExists(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (Throwable $exception) {
            Log::debug('SystemSettingConfigMapper: Schema-Check übersprungen', [
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function applyMailConfig(): void
    {
        if ($mailDriver = $this->string('mail_transport')) {
            config(['mail.default' => $mailDriver]);
        }

        if ($fromEmail = $this->string('mail_from_email')) {
            config(['mail.from.address' => $fromEmail]);
        }

        if ($fromName = $this->string('mail_from_name')) {
            config(['mail.from.name' => $fromName]);
        }

        $smtpOverrides = [];

        if ($host = $this->string('mail_smtp_host')) {
            $smtpOverrides['mail.mailers.smtp.host'] = $host;
        }

        if (($port = $this->int('mail_smtp_port')) !== null) {
            $smtpOverrides['mail.mailers.smtp.port'] = $port;
        }

        if ($username = $this->string('mail_smtp_username')) {
            $smtpOverrides['mail.mailers.smtp.username'] = $username;
        }

        if ($password = $this->string('mail_smtp_password')) {
            $smtpOverrides['mail.mailers.smtp.password'] = $password;
        }

        if (($timeout = $this->int('mail_smtp_timeout')) !== null) {
            $smtpOverrides['mail.mailers.smtp.timeout'] = $timeout;
        }

        if ($scheme = $this->string('mail_smtp_encryption')) {
            $smtpOverrides['mail.mailers.smtp.scheme'] = $scheme === 'none' ? null : $scheme;
        }

        if (! empty($smtpOverrides)) {
            config($smtpOverrides);
        }

        $verifyPeer = $this->bool('mail_smtp_verify_peer');
        if ($verifyPeer !== null) {
            config(['services.mail.smtp_verify_peer' => $verifyPeer]);
        }
    }

    private function applyDhlTracking(): void
    {
        $overrides = [];

        if ($baseUrl = $this->string('dhl_base_url')) {
            $overrides['services.dhl.base_url'] = $baseUrl;
        }

        if ($apiKey = $this->string('dhl_api_key')) {
            $overrides['services.dhl.api_key'] = $apiKey;
        }

        if (($timeout = $this->float('dhl_timeout')) !== null) {
            $overrides['services.dhl.timeout'] = $timeout;
        }

        if (($connect = $this->float('dhl_connect_timeout')) !== null) {
            $overrides['services.dhl.connect_timeout'] = $connect;
        }

        if (($verify = $this->bool('dhl_verify_ssl')) !== null) {
            $overrides['services.dhl.verify'] = $verify;
        }

        if (! empty($overrides)) {
            config($overrides);
        }
    }

    private function applyDhlAuth(): void
    {
        $overrides = [];

        if ($baseUrl = $this->string('dhl_auth_base_url')) {
            $overrides['services.dhl_auth.base_url'] = $baseUrl;
        }

        if ($username = $this->string('dhl_auth_username')) {
            $overrides['services.dhl_auth.username'] = $username;
        }

        if ($password = $this->string('dhl_auth_password')) {
            $overrides['services.dhl_auth.password'] = $password;
        }

        if ($path = $this->string('dhl_auth_path')) {
            $overrides['services.dhl_auth.path'] = $path;
        }

        if (($ttl = $this->int('dhl_auth_token_cache_ttl')) !== null) {
            $overrides['services.dhl_auth.token_cache_ttl'] = $ttl;
        }

        if (! empty($overrides)) {
            config($overrides);
        }
    }

    private function applyDhlFreight(): void
    {
        $overrides = [];

        if ($baseUrl = $this->string('dhl_freight_base_url')) {
            $overrides['services.dhl_freight.base_url'] = $baseUrl;
        }

        if ($apiKey = $this->string('dhl_freight_api_key')) {
            $overrides['services.dhl_freight.api_key'] = $apiKey;
        }

        if ($secret = $this->string('dhl_freight_api_secret')) {
            $overrides['services.dhl_freight.api_secret'] = $secret;
        }

        if ($auth = $this->string('dhl_freight_auth')) {
            $overrides['services.dhl_freight.auth'] = $auth;
        }

        if (($timeout = $this->float('dhl_freight_timeout')) !== null) {
            $overrides['services.dhl_freight.timeout'] = $timeout;
        }

        if (($connect = $this->float('dhl_freight_connect_timeout')) !== null) {
            $overrides['services.dhl_freight.connect_timeout'] = $connect;
        }

        if (($verify = $this->bool('dhl_freight_verify_ssl')) !== null) {
            $overrides['services.dhl_freight.verify'] = $verify;
        }

        if (! empty($overrides)) {
            config($overrides);
        }
    }

    private function applyDhlPush(): void
    {
        $overrides = [];

        if ($baseUrl = $this->string('dhl_push_base_url')) {
            $overrides['services.dhl_push.base_url'] = $baseUrl;
        }

        if ($apiKey = $this->string('dhl_push_api_key')) {
            $overrides['services.dhl_push.api_key'] = $apiKey;
        }

        if ($header = $this->string('dhl_push_api_key_header')) {
            $overrides['services.dhl_push.api_key_header'] = $header;
        }

        if (! empty($overrides)) {
            config($overrides);
        }
    }

    private function string(string $key): ?string
    {
        $value = $this->settings->get($key);

        return $value !== null && $value !== '' ? $value : null;
    }

    private function int(string $key): ?int
    {
        $value = $this->settings->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function float(string $key): ?float
    {
        $value = $this->settings->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function bool(string $key): ?bool
    {
        $value = $this->settings->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array(strtolower($value), ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
    }
}
