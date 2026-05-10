<?php

namespace App\Console\Commands\Identity;

use App\Application\Identity\UserAccountService;
use App\Support\Security\PasswordPolicy;
use App\Support\Security\SecurityContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateUserCommand extends Command
{
    protected $signature = 'identity:user:create
        {username : Unique username}
        {--password= : Plain password (random if omitted)}
        {--role=user : Role name}
        {--email= : Optional email address}
        {--display-name= : Optional display name}
        {--no-force-change : Do not require password change on first login}';

    protected $description = 'Create a new application user account';

    public function __construct(private readonly UserAccountService $accounts)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = $this->option('password') ?: $this->generatePassword();

        Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', 'max:255', PasswordPolicy::rule()]]
        )->validate();

        $role = (string) $this->option('role');
        $email = $this->option('email');
        $displayName = $this->option('display-name');
        $mustChange = ! $this->option('no-force-change');

        $user = $this->accounts->createUser(
            $username,
            $password,
            $role,
            is_string($displayName) ? $displayName : null,
            is_string($email) ? $email : null,
            $mustChange,
            false,
            SecurityContext::system('console:identity:user:create')
        );

        $this->info(sprintf(
            'User "%s" created with role "%s".%s',
            $user->username(),
            $user->role(),
            $this->option('password') ? '' : ' Generated password: '.$password
        ));

        return self::SUCCESS;
    }

    private function generatePassword(int $length = 16): string
    {
        return PasswordPolicy::generate($length);
    }
}
