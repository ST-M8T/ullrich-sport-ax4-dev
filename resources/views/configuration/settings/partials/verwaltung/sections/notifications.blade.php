<div class="stack stack-lg">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="h6 mb-1">Benachrichtigungs-Queue</h4>
            <p class="text-muted mb-0 small">Manuelle Benachrichtigungen erstellen und Queue verwalten.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="toggleCreateForm('notificationCreateForm')">
            <span id="notificationCreateFormToggle" data-original-text="Neue Benachrichtigung erstellen">Neue Benachrichtigung erstellen</span>
        </button>
    </div>

    <div id="notificationCreateForm" class="mb-4" style="display: none;">
        <div class="card card-body bg-light">
            <h6 class="mb-3">Manuelle Benachrichtigung erstellen</h6>
            @include('configuration.settings.partials.notification-form', [
                'action' => route('configuration-notifications.store'),
                'method' => 'POST',
                'notification' => null,
                'cancelTarget' => '#notificationCreateForm',
            ])
        </div>
    </div>

    <form method="post" action="{{ route('configuration-notifications.dispatch') }}" class="d-flex gap-2 mb-4">
        @csrf
        <div class="input-group" style="max-width: 200px;">
            <span class="input-group-text">Limit</span>
            <input type="number" name="limit" value="50" min="1" max="200" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Pending Benachrichtigungen senden</button>
    </form>

    @if(empty($notifications))
        <div class="alert alert-info mb-0">
            <small>Keine Benachrichtigungen vorhanden.</small>
        </div>
    @else
        <x-ui.data-table dense striped hover>
            <thead class="table-light">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Typ</th>
                    <th scope="col">Channel</th>
                    <th scope="col">Status</th>
                    <th scope="col">Geplant</th>
                    <th scope="col">Gesendet</th>
                    <th scope="col">Fehler</th>
                    <th scope="col">Erstellt</th>
                    <th scope="col" class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notifications as $notification)
                    <tr>
                        <td><strong>{{ $notification->id()->toInt() }}</strong></td>
                        <td><code class="small">{{ $notification->notificationType() }}</code></td>
                        <td>{{ $notification->channel() ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $notification->status() === 'sent' ? 'success' : ($notification->status() === 'pending' ? 'warning' : 'secondary') }} text-uppercase" aria-label="Status: {{ $notification->status() }}">
                                {{ $notification->status() }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $notification->scheduledAt()?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="small text-muted">{{ $notification->sentAt()?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="small {{ $notification->errorMessage() ? 'text-danger' : 'text-muted' }}">
                            {{ Str::limit($notification->errorMessage() ?? '—', 50) }}
                        </td>
                        <td class="small text-muted">{{ $notification->createdAt()->format('d.m.Y H:i') }}</td>
                        <td class="text-end">
                            <form method="post" action="{{ route('configuration-notifications.redispatch', ['notification' => $notification->id()->toInt()]) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    Erneut senden
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.data-table>
    @endif
</div>
