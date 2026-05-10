<?php

namespace App\Infrastructure\Identity;

use App\Domain\Identity\Contracts\IdentityServiceGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpIdentityServiceGateway implements IdentityServiceGateway
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiToken = null
    ) {
        if (trim($this->baseUrl) === '') {
            throw new RuntimeException('Identity service base URL must be configured.');
        }
    }

    public function requiresTwoFactor(string $username): bool
    {
        try {
            $response = $this->client()->get(
                $this->uri(sprintf('/users/%s/two-factor', rawurlencode($username)))
            );

            if ($response->status() === 404) {
                return false;
            }

            $response->throw();

            return (bool) ($response->json('required') ?? false);
        } catch (RequestException $exception) {
            throw new RuntimeException('Unable to determine two-factor requirement.', 0, $exception);
        }
    }

    public function verifyTwoFactorCode(string $username, string $code): bool
    {
        try {
            $response = $this->client()->post(
                $this->uri(sprintf('/users/%s/two-factor/verify', rawurlencode($username))),
                ['code' => $code]
            );

            if ($response->status() === 404) {
                return false;
            }

            $response->throw();

            return (bool) ($response->json('valid') ?? false);
        } catch (RequestException $exception) {
            throw new RuntimeException('Two-factor verification failed.', 0, $exception);
        }
    }

    public function triggerPasswordReset(string $username): void
    {
        try {
            $response = $this->client()->post(
                $this->uri(sprintf('/users/%s/password/reset', rawurlencode($username)))
            );

            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('Password reset trigger failed.', 0, $exception);
        }
    }

    public function notifyPasswordChanged(string $username): void
    {
        try {
            $response = $this->client()->post(
                $this->uri(sprintf('/users/%s/password/confirm', rawurlencode($username)))
            );

            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('Password change notification failed.', 0, $exception);
        }
    }

    private function client(): PendingRequest
    {
        $client = Http::acceptJson()->baseUrl(rtrim($this->baseUrl, '/'));

        if (is_string($this->apiToken) && $this->apiToken !== '') {
            $client = $client->withToken($this->apiToken);
        }

        return $client->timeout(5);
    }

    private function uri(string $path): string
    {
        return ltrim($path, '/');
    }
}
