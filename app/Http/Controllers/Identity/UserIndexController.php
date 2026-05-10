<?php

namespace App\Http\Controllers\Identity;

use App\Application\Identity\Authorization\RoleManager;
use App\Application\Identity\Queries\SearchUsers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class UserIndexController
{
    public function __construct(
        private readonly SearchUsers $searchUsers,
        private readonly RoleManager $roles,
    ) {}

    public function index(Request $request): View
    {
        $viewFilters = [
            'username' => $request->string('username')->trim(),
            'role' => $request->string('role')->trim(),
            'disabled' => $request->filled('disabled') ? (string) $request->input('disabled') : '',
            'must_change_password' => $request->filled('must_change_password') ? (string) $request->input('must_change_password') : '',
        ];

        $searchFilters = [
            'username' => $viewFilters['username'],
            'role' => $viewFilters['role'],
        ];

        if ($viewFilters['disabled'] !== '') {
            $searchFilters['disabled'] = (bool) (int) $viewFilters['disabled'];
        }

        if ($viewFilters['must_change_password'] !== '') {
            $searchFilters['must_change_password'] = (bool) (int) $viewFilters['must_change_password'];
        }

        $users = ($this->searchUsers)($searchFilters);

        return view('identity.users.index', [
            'users' => $users,
            'filters' => $viewFilters,
            'roleOptions' => $this->roles->selectableRoles(),
        ]);
    }
}
