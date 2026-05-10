<div class="stack stack-lg">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="h6 mb-1">Benutzer & Rollen</h4>
            <p class="text-muted mb-0 small">Admin-Konten und Rollen verwalten.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="toggleCreateForm('userCreateForm')">
            <span id="userCreateFormToggle" data-original-text="Neuen Benutzer anlegen">Neuen Benutzer anlegen</span>
        </button>
    </div>

    <div id="userCreateForm" class="mb-4" style="display: none;">
        <div class="card card-body bg-light">
            <h6 class="mb-3">Neuen Benutzer anlegen</h6>
            @include('configuration.settings.partials.user-form', [
                'action' => route('identity-users.store'),
                'method' => 'POST',
                'user' => null,
                'cancelTarget' => '#userCreateForm',
            ])
        </div>
    </div>

    @if(empty($users))
        <div class="alert alert-info mb-0">
            <small>Keine Benutzer vorhanden.</small>
        </div>
    @else
        <div class="table-responsive">
            <x-ui.data-table dense striped hover>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Name</th>
                        <th scope="col">E-Mail</th>
                        <th scope="col">Rolle</th>
                        <th scope="col">Status</th>
                        <th scope="col">Letzter Login</th>
                        <th scope="col">Erstellt</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        @php
                            $statusBadge = $user->disabled() ? 'bg-danger' : 'bg-success';
                            $roleSlug = $user->role();
                            $roleLabel = ($roleOptions[$roleSlug]['label'] ?? $roleSlug);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $user->username() }}</strong>
                                @if($user->requiresPasswordChange())
                                    <span class="badge bg-warning text-dark ms-1">PW erforderlich</span>
                                @endif
                            </td>
                            <td>{{ $user->displayName() ?? '—' }}</td>
                            <td>{{ $user->email() ?? '—' }}</td>
                            <td>
                                <span class="badge bg-secondary text-uppercase">{{ $roleSlug }}</span>
                                <span class="d-block small text-muted">{{ $roleLabel }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $statusBadge }}">{{ $user->disabled() ? 'Deaktiviert' : 'Aktiv' }}</span>
                            </td>
                            <td class="small text-muted">{{ $user->lastLoginAt()?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td class="small text-muted">{{ $user->createdAt()->format('d.m.Y H:i') }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleRow('userEdit{{ $user->id()->toInt() }}')">
                                        Bearbeiten
                                    </button>
                                    <a href="{{ route('identity-users.show', ['user' => $user->id()->toInt()]) }}" class="btn btn-outline-info btn-sm">
                                        Details
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr id="userEdit{{ $user->id()->toInt() }}" style="display: none;">
                            <td colspan="8" class="bg-light">
                                <div class="p-3">
                                    <h6 class="mb-3">Benutzer bearbeiten: {{ $user->username() }}</h6>
                                    @include('configuration.settings.partials.user-form', [
                                        'action' => route('identity-users.update', ['user' => $user->id()->toInt()]),
                                        'method' => 'PUT',
                                        'user' => $user,
                                        'cancelTarget' => '#userEdit' . $user->id()->toInt(),
                                    ])
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.data-table>
        </div>
    @endif
</div>

