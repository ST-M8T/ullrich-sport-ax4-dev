<div class="stack stack-lg">
    <div class="grid-auto grid-auto-lg">
        <div class="data-indicator" data-tone="info">
            <span class="data-indicator__label">Log Channel</span>
            <span class="data-indicator__value">{{ $defaultChannel }}</span>
        </div>
        <div class="data-indicator" data-tone="info">
            <span class="data-indicator__label">Verzeichnisse</span>
            <span class="data-indicator__value">{{ $directories->count() }}</span>
        </div>
    </div>

    <div>
        <h4 class="h6 mb-2">Verzeichnisdetails</h4>
        <x-ui.data-table dense>
            <thead>
            <tr>
                <th scope="col">Pfad</th>
                <th scope="col">Existiert</th>
                <th scope="col">Schreibbar</th>
                <th scope="col">Dateien</th>
                <th scope="col">Größe</th>
            </tr>
            </thead>
            <tbody>
            @forelse($directories as $directory)
                <tr>
                    <td><code>{{ $directory['path'] ?? '-' }}</code></td>
                    <td>{{ ($directory['exists'] ?? false) ? 'Ja' : 'Nein' }}</td>
                    <td>{{ ($directory['writable'] ?? false) ? 'Ja' : 'Nein' }}</td>
                    <td>{{ $directory['file_count'] ?? 0 }}</td>
                    <td>{{ isset($directory['size_bytes']) ? number_format($directory['size_bytes'] / 1024, 1, ',', '.') . ' KB' : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-3">Keine Logdaten vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </x-ui.data-table>
    </div>
</div>
