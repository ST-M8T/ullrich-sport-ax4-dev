<?php

namespace App\Http\Controllers\Identity;

use App\Application\Identity\Authorization\RoleManager;
use App\Application\Identity\Queries\GetUserById;
use App\Application\Identity\UserAccountService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Support\Security\PasswordPolicy;
use App\Support\Security\SecurityContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\Rule;

final class UserManagementController
{
    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly GetUserById $getUser,
        private readonly Redirector $redirector,
        private readonly RoleManager $roles,
    ) {}

    public function create(): View
    {
        return view('identity.users.create', [
            'availableRoles' => $this->roles->selectableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:191', 'alpha_dash', 'unique:users,username'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:191', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in($this->availableRoleValues())],
            'password' => ['required', 'string', 'max:255', 'confirmed', PasswordPolicy::rule()],
            'must_change_password' => ['nullable', 'boolean'],
            'disabled' => ['nullable', 'boolean'],
        ]);

        $context = SecurityContext::fromRequest($request);

        $user = $this->accounts->createUser(
            $data['username'],
            $data['password'],
            $data['role'],
            $data['display_name'] ?? null,
            $data['email'] ?? null,
            $request->boolean('must_change_password', true),
            $request->boolean('disabled', false),
            $context,
        );

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'identity-users'])
            ->with('success', 'Benutzer wurde erstellt.');
    }

    public function edit(int $user): View|RedirectResponse
    {
        $identifier = Identifier::fromInt($user);

        $entity = ($this->getUser)($identifier);

        if ($entity === null) {
            return $this->redirector->route('identity-users')->with('error', 'Benutzer nicht gefunden.');
        }

        return view('identity.users.edit', [
            'user' => $entity,
            'availableRoles' => $this->roles->selectableRoles(),
        ]);
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $identifier = Identifier::fromInt($user);

        $existing = ($this->getUser)($identifier);

        if ($existing === null) {
            return $this->redirector->route('identity-users')->with('error', 'Benutzer nicht gefunden.');
        }

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:191'],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($existing->id()->toInt()),
            ],
            'role' => ['required', 'string', Rule::in($this->availableRoleValues())],
            'password' => ['nullable', 'string', 'max:255', 'confirmed', PasswordPolicy::rule()],
            'must_change_password' => ['nullable', 'boolean'],
            'disabled' => ['nullable', 'boolean'],
        ]);

        $context = SecurityContext::fromRequest($request);

        $updated = $this->accounts->updateUser(
            $identifier,
            [
                'display_name' => $data['display_name'] ?? null,
                'email' => $data['email'] ?? null,
                'role' => $data['role'],
                'must_change_password' => $request->boolean('must_change_password'),
                'disabled' => $request->boolean('disabled'),
            ],
            $context,
        );

        if ($updated === null) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'identity-users'])
                ->with('error', 'Aktualisierung fehlgeschlagen. Bitte erneut versuchen.');
        }

        if (! empty($data['password'])) {
            $this->accounts->changePassword(
                $identifier,
                $data['password'],
                $request->boolean('must_change_password'),
                $context
            );
        }

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'verwaltung', 'verwaltung_tab' => 'identity-users'])
            ->with('success', 'Benutzer wurde aktualisiert.');
    }

    public function resetPassword(Request $request, int $user): RedirectResponse
    {
        $identifier = Identifier::fromInt($user);

        $entity = ($this->getUser)($identifier);

        if ($entity === null) {
            return $this->redirector->route('identity-users')->with('error', 'Benutzer nicht gefunden.');
        }

        $data = $request->validate([
            'new_password' => ['required', 'string', 'max:255', 'confirmed', PasswordPolicy::rule()],
            'require_password_change' => ['nullable', 'boolean'],
        ]);

        $context = SecurityContext::fromRequest($request);

        $this->accounts->changePassword(
            $identifier,
            $data['new_password'],
            $request->boolean('require_password_change', true),
            $context,
        );

        return $this->redirector
            ->route('identity-users.show', ['user' => $entity->id()->toInt()])
            ->with('success', 'Passwort wurde zurückgesetzt.');
    }

    public function updateStatus(Request $request, int $user): RedirectResponse
    {
        $identifier = Identifier::fromInt($user);

        $entity = ($this->getUser)($identifier);

        if ($entity === null) {
            return $this->redirector->route('identity-users')->with('error', 'Benutzer nicht gefunden.');
        }

        $data = $request->validate([
            'disabled' => ['required', 'boolean'],
        ]);

        $context = SecurityContext::fromRequest($request);

        $this->accounts->setDisabled($identifier, (bool) $data['disabled'], $context);

        $message = $data['disabled'] ? 'Benutzer wurde deaktiviert.' : 'Benutzer wurde aktiviert.';

        return $this->redirector
            ->route('identity-users.show', ['user' => $entity->id()->toInt()])
            ->with('info', $message);
    }

    /**
     * @return list<string>
     */
    private function availableRoleValues(): array
    {
        return array_keys($this->roles->allRoles());
    }
}
