<?php

namespace App\Http\Controllers\Identity;

use App\Application\Identity\Authorization\RoleManager;
use App\Application\Identity\Queries\GetUserById;
use App\Application\Identity\Queries\ListRecentLoginAttempts;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

final class UserDetailController
{
    public function __construct(
        private readonly GetUserById $getUser,
        private readonly ListRecentLoginAttempts $listLoginAttempts,
        private readonly Redirector $redirector,
        private readonly RoleManager $roles,
    ) {}

    public function show(Request $request, int $user): View|RedirectResponse
    {
        $identifier = Identifier::fromInt($user);
        $entity = ($this->getUser)($identifier);

        if ($entity === null) {
            return $this->redirector->route('identity-users')->with('error', 'Benutzer nicht gefunden.');
        }

        $attempts = ($this->listLoginAttempts)($entity->username(), (int) $request->integer('attempts', 10));

        return view('identity.users.show', [
            'user' => $entity,
            'loginAttempts' => $attempts,
            'roleMetadata' => [
                'label' => $this->roles->labelForRole($entity->role()),
                'description' => $this->roles->descriptionForRole($entity->role()),
            ],
            'permissionDetails' => $this->mapPermissions($entity->permissions()),
        ]);
    }

    /**
     * @param  array<int,string>  $permissions
     * @return list<array{permission:string,label:string,description:?string}>
     */
    private function mapPermissions(array $permissions): array
    {
        $details = [];

        foreach ($permissions as $permission) {
            if ($permission === '*') {
                $details[] = [
                    'permission' => '*',
                    'label' => 'Alle Berechtigungen',
                    'description' => 'Umfasst sämtliche verfügbaren Aktionen.',
                ];

                continue;
            }

            $metadata = $this->roles->permissionMetadata($permission);

            $details[] = [
                'permission' => $permission,
                'label' => $metadata['label'] ?? $permission,
                'description' => $metadata['description'] ?? null,
            ];
        }

        return $details;
    }
}
