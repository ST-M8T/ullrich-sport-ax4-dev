<?php

namespace Tests\Feature\Identity;

use App\Application\Identity\AuthenticationService;
use App\Application\Identity\UserAccountService;
use App\Domain\Identity\Contracts\IdentityServiceGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Fakes\FakeIdentityServiceGateway;
use Tests\TestCase;

final class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login:lockme|127.0.0.1');
        RateLimiter::clear('login:twofactor|127.0.0.1');
    }

    public function test_login_rate_limiting_blocks_after_threshold(): void
    {
        config()->set('security.rate_limiting.login.max_attempts', 2);
        config()->set('security.rate_limiting.login.decay_seconds', 600);

        $fakeGateway = new FakeIdentityServiceGateway;
        $this->app->instance(IdentityServiceGateway::class, $fakeGateway);

        /** @var UserAccountService $accounts */
        $accounts = $this->app->make(UserAccountService::class);
        $accounts->createUser('lockme', 'LockMe!123456', 'viewer', null, null, false, false);

        /** @var AuthenticationService $auth */
        $auth = $this->app->make(AuthenticationService::class);

        $auth->attempt('lockme', 'wrong-pass', '127.0.0.1', 'PHPUnit');
        $auth->attempt('lockme', 'wrong-pass', '127.0.0.1', 'PHPUnit');

        $result = $auth->attempt('lockme', 'wrong-pass', '127.0.0.1', 'PHPUnit');

        $this->assertFalse($result['success']);
        $this->assertSame('too_many_attempts', $result['error']);
        $this->assertArrayHasKey('retry_after_seconds', $result);
        $this->assertGreaterThan(0, $result['retry_after_seconds']);
    }

    public function test_two_factor_flow_requires_valid_code(): void
    {
        config()->set('security.rate_limiting.login.max_attempts', 5);
        config()->set('security.rate_limiting.login.decay_seconds', 600);

        $fakeGateway = new FakeIdentityServiceGateway;
        $fakeGateway->requiresTwoFactor = true;
        $fakeGateway->allowCode('twofactor', '123456');

        $this->app->instance(IdentityServiceGateway::class, $fakeGateway);

        /** @var UserAccountService $accounts */
        $accounts = $this->app->make(UserAccountService::class);
        $accounts->createUser('twofactor', 'Password!123456', 'viewer', null, null, false, false);

        /** @var AuthenticationService $auth */
        $auth = $this->app->make(AuthenticationService::class);

        $missingCode = $auth->attempt('twofactor', 'Password!123456', '127.0.0.1', 'PHPUnit');
        $this->assertFalse($missingCode['success']);
        $this->assertSame('two_factor_required', $missingCode['error']);
        $this->assertTrue($missingCode['two_factor_required']);

        $invalidCode = $auth->attempt('twofactor', 'Password!123456', '127.0.0.1', 'PHPUnit', '000000');
        $this->assertFalse($invalidCode['success']);
        $this->assertSame('two_factor_invalid', $invalidCode['error']);

        $valid = $auth->attempt('twofactor', 'Password!123456', '127.0.0.1', 'PHPUnit', '123456');
        $this->assertTrue($valid['success']);
        $this->assertTrue($valid['two_factor_required']);
    }

    public function test_password_change_notifies_identity_service(): void
    {
        $fakeGateway = new FakeIdentityServiceGateway;
        $this->app->instance(IdentityServiceGateway::class, $fakeGateway);

        /** @var UserAccountService $accounts */
        $accounts = $this->app->make(UserAccountService::class);
        $user = $accounts->createUser('notify', 'Notify!123456', 'viewer', null, null, false, false);

        $accounts->changePassword($user->id(), 'Updated!123456', true);

        $this->assertContains('notify', $fakeGateway->notifications['password_changed']);
    }
}
