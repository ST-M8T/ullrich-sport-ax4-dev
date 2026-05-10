<?php

namespace Tests\Feature\Console;

use App\Application\Identity\Authorization\RoleManager;
use App\Application\Identity\UserAccountService;
use App\Application\Identity\UserCreationService;
use App\Application\Identity\UserPasswordService;
use App\Application\Identity\UserUpdateService;
use App\Application\Monitoring\AuditLogger;
use App\Domain\Identity\Contracts\IdentityServiceGateway;
use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\Contracts\UserRepository;
use App\Domain\Identity\PasswordHash;
use App\Domain\Identity\User;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

final class IdentityCreateUserCommandTest extends TestCase
{
    public function test_create_user_command_uses_provided_password(): void
    {
        $password = 'S0Up3r#Safe!2024';

        $service = $this->bootService($password, Identifier::fromInt(15));
        $this->app->instance(UserAccountService::class, $service);

        $exitCode = Artisan::call('identity:user:create', [
            'username' => 'operator',
            '--password' => $password,
            '--role' => 'operations',
            '--email' => 'op@example.test',
        ]);

        $output = Artisan::output();

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('User "operator" created with role "operations".', $output);
        $this->assertStringNotContainsString('Generated password:', $output);
    }

    public function test_create_user_command_generates_password_when_missing(): void
    {
        $service = $this->bootService(null, Identifier::fromInt(21));
        $this->app->instance(UserAccountService::class, $service);

        $exitCode = Artisan::call('identity:user:create', [
            'username' => 'worker',
            '--role' => 'viewer',
        ]);

        $output = Artisan::output();

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('User "worker" created with role "viewer".', $output);
        $this->assertStringContainsString('Generated password:', $output);
    }

    private function bootService(?string $expectedPassword, Identifier $id): UserAccountService
    {
        $users = Mockery::mock(UserRepository::class);
        $hasher = Mockery::mock(PasswordHasher::class);
        $audit = Mockery::mock(AuditLogger::class);
        $identity = Mockery::mock(IdentityServiceGateway::class);
        $roles = app(RoleManager::class);

        $users->shouldReceive('nextIdentity')
            ->once()
            ->andReturn($id);

        $hasher->shouldReceive('hash')
            ->once()
            ->with($expectedPassword ?? Mockery::type('string'))
            ->andReturn(PasswordHash::fromString('hashed-password'));

        $users->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (User $user): bool {
                return $user->username() !== '';
            }));

        $audit->shouldReceive('log')->once();
        $identity->shouldReceive('notifyPasswordChanged')->zeroOrMoreTimes();

        $creator = new UserCreationService($users, $hasher, $roles, $audit);
        $passwords = new UserPasswordService($users, $hasher, $identity, $audit);
        $updates = new UserUpdateService($users, $roles, $audit);

        return new UserAccountService($creator, $passwords, $updates);
    }
}
