<?php

namespace App\Infrastructure\Persistence\Identity\Eloquent;

use App\Application\Identity\Authorization\RoleManager;
use App\Domain\Identity\PasswordHash;
use App\Domain\Identity\User as IdentityUser;
use App\Domain\Shared\ValueObjects\Identifier;
use Database\Factories\UserModelFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property \DateTimeInterface|null $last_login_at
 */
class UserModel extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserModelFactory> */
    use HasFactory;

    use Notifiable;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'display_name',
        'email',
        'password_hash',
        'role',
        'must_change_password',
        'disabled',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * @var array<string,string>
     */
    protected $casts = [
        'must_change_password' => 'bool',
        'disabled' => 'bool',
        'last_login_at' => 'datetime',
    ];

    /**
     * @var array<int,string>|null
     */
    private ?array $cachedPermissions = null;

    private static ?RoleManager $roleManager = null;

    public static function setRoleManager(RoleManager $roleManager): void
    {
        self::$roleManager = $roleManager;
    }

    private static function roleManager(): RoleManager
    {
        if (! self::$roleManager) {
            throw new \RuntimeException('RoleManager not set for UserModel. Call UserModel::setRoleManager() during boot.');
        }

        return self::$roleManager;
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function setRoleAttribute(mixed $value): void
    {
        $this->attributes['role'] = strtolower(trim((string) $value));
        $this->cachedPermissions = null;
    }

    public function setUsernameAttribute(mixed $value): void
    {
        $this->attributes['username'] = strtolower(trim((string) $value));
    }

    public function hasPermission(string $permission): bool
    {
        $permission = strtolower(trim($permission));
        $permissions = $this->permissions();

        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * @return array<int,string>
     */
    public function permissions(): array
    {
        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = self::roleManager()->permissionsForRole($this->role);
        }

        return $this->cachedPermissions;
    }

    protected static function newFactory(): UserModelFactory
    {
        return UserModelFactory::new();
    }

    public function toIdentityUser(): IdentityUser
    {
        $roles = self::roleManager();

        return IdentityUser::hydrate(
            Identifier::fromInt((int) $this->getKey()),
            (string) $this->username,
            $this->display_name !== null ? (string) $this->display_name : null,
            $this->email !== null ? (string) $this->email : null,
            PasswordHash::fromString((string) $this->password_hash),
            (string) $this->role,
            (bool) $this->must_change_password,
            (bool) $this->disabled,
            $this->last_login_at instanceof \DateTimeInterface
                ? DateTimeImmutable::createFromInterface($this->last_login_at)
                : null,
            $this->created_at instanceof \DateTimeInterface
                ? DateTimeImmutable::createFromInterface($this->created_at)
                : new DateTimeImmutable,
            $this->updated_at instanceof \DateTimeInterface
                ? DateTimeImmutable::createFromInterface($this->updated_at)
                : new DateTimeImmutable,
            $roles->permissionsForRole((string) $this->role),
        );
    }
}
