<?php

namespace App\Application\Identity\Authorization;

use InvalidArgumentException;

final class RoleManager
{
    private const WILDCARD = '*';

    /**
     * @var array<string,array{label:string,description:?string,permissions:array<int,string>,inherits:array<int,string>}>
     */
    private array $roles;

    /**
     * @var array<string,array{label:string,description:?string}>
     */
    private array $permissions;

    /**
     * @var array<string,array<int,string>>
     */
    private array $resolvedPermissions = [];

    private string $defaultRole;

    /**
     * @param  array<string,mixed>  $roleDefinitions
     * @param  array<string,mixed>  $permissionDefinitions
     */
    public function __construct(array $roleDefinitions, array $permissionDefinitions, ?string $defaultRole = null)
    {
        $this->permissions = $this->normalizePermissions($permissionDefinitions);
        $this->roles = $this->normalizeRoles($roleDefinitions);
        $this->defaultRole = $this->normalizeRole($defaultRole ?? '');

        $this->validateRoles();
    }

    /**
     * @return array<string,array{label:string,description:?string,permissions:array<int,string>,inherits:array<int,string>}>
     */
    public function allRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return list<array{value:string,label:string,description:?string}>
     */
    public function selectableRoles(): array
    {
        $options = [];
        foreach ($this->roles as $slug => $definition) {
            $options[] = [
                'value' => $slug,
                'label' => $definition['label'],
                'description' => $definition['description'],
            ];
        }

        return $options;
    }

    public function normalizeRole(string $role): string
    {
        return strtolower(trim($role));
    }

    public function normalizePermission(string $permission): string
    {
        return strtolower(trim($permission));
    }

    /**
     * @return array<int,string>
     */
    public function permissionsForRole(string $role): array
    {
        $slug = $this->normalizeRole($role);
        if (! isset($this->roles[$slug])) {
            return [];
        }

        if (! isset($this->resolvedPermissions[$slug])) {
            $this->resolvedPermissions[$slug] = $this->resolvePermissions($slug, []);
        }

        return $this->resolvedPermissions[$slug];
    }

    public function hasPermission(string $role, string $permission): bool
    {
        $permission = $this->normalizePermission($permission);
        $permissions = $this->permissionsForRole($role);

        if (in_array(self::WILDCARD, $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * @return array<int,string>
     */
    public function allPermissionSlugs(): array
    {
        return array_keys($this->permissions);
    }

    public function defaultRole(): ?string
    {
        return $this->defaultRole !== '' ? $this->defaultRole : null;
    }

    public function labelForRole(string $role): string
    {
        $slug = $this->normalizeRole($role);

        return $this->roles[$slug]['label'] ?? $role;
    }

    public function descriptionForRole(string $role): ?string
    {
        $slug = $this->normalizeRole($role);

        return $this->roles[$slug]['description'] ?? null;
    }

    public function ensureRoleExists(string $role): string
    {
        $slug = $this->normalizeRole($role);
        if (! isset($this->roles[$slug])) {
            throw new InvalidArgumentException(sprintf('Unbekannte Rolle "%s".', $role));
        }

        return $slug;
    }

    /**
     * @return array{label:string,description:?string}|null
     */
    public function permissionMetadata(string $permission): ?array
    {
        $slug = $this->normalizePermission($permission);

        return $this->permissions[$slug] ?? null;
    }

    /**
     * @param  array<string,mixed>  $definitions
     * @return array<string,array{label:string,description:?string,permissions:array<int,string>,inherits:array<int,string>}>
     */
    private function normalizeRoles(array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $role => $definition) {
            $slug = $this->normalizeRole((string) $role);

            if ($slug === '') {
                continue;
            }

            $permissions = [];
            foreach ((array) ($definition['permissions'] ?? []) as $permission) {
                $permissions[] = $this->normalizePermission((string) $permission);
            }

            $inherits = [];
            foreach ((array) ($definition['inherits'] ?? []) as $inherited) {
                $normalizedInherited = $this->normalizeRole((string) $inherited);
                if ($normalizedInherited !== '' && $normalizedInherited !== $slug) {
                    $inherits[] = $normalizedInherited;
                }
            }

            $normalized[$slug] = [
                'label' => (string) ($definition['label'] ?? ucfirst($slug)),
                'description' => isset($definition['description']) ? (string) $definition['description'] : null,
                'permissions' => array_values(array_filter($permissions, fn (string $value): bool => $value !== '')),
                'inherits' => array_values(array_unique($inherits)),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $definitions
     * @return array<string,array{label:string,description:?string}>
     */
    private function normalizePermissions(array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $permission => $definition) {
            if (is_int($permission)) {
                $permission = (string) $definition;
                $definition = [];
            }

            $slug = $this->normalizePermission((string) $permission);
            if ($slug === '') {
                continue;
            }

            $normalized[$slug] = [
                'label' => (string) ($definition['label'] ?? $slug),
                'description' => isset($definition['description']) ? (string) $definition['description'] : null,
            ];
        }

        return $normalized;
    }

    private function validateRoles(): void
    {
        foreach ($this->roles as $role => $definition) {
            foreach ($definition['inherits'] as $inherited) {
                if (! isset($this->roles[$inherited])) {
                    throw new InvalidArgumentException(sprintf(
                        'Die Rolle "%s" erbt von unbekannter Rolle "%s".',
                        $role,
                        $inherited
                    ));
                }
            }

            foreach ($definition['permissions'] as $permission) {
                if ($permission === self::WILDCARD) {
                    continue;
                }

                if (! isset($this->permissions[$permission])) {
                    throw new InvalidArgumentException(sprintf(
                        'Die Rolle "%s" referenziert die unbekannte Berechtigung "%s".',
                        $role,
                        $permission
                    ));
                }
            }
        }
    }

    /**
     * @param  array<int,string>  $visited
     * @return array<int,string>
     */
    private function resolvePermissions(string $role, array $visited): array
    {
        if (in_array($role, $visited, true)) {
            return [];
        }

        $visited[] = $role;
        $definition = $this->roles[$role] ?? ['permissions' => [], 'inherits' => []];

        $permissions = [];

        foreach ($definition['inherits'] as $inherited) {
            $permissions = array_merge($permissions, $this->resolvePermissions($inherited, $visited));
        }

        $permissions = array_merge($permissions, $definition['permissions']);

        $permissions = array_values(array_unique($permissions));

        if (in_array(self::WILDCARD, $permissions, true)) {
            return [self::WILDCARD];
        }

        return $permissions;
    }
}
