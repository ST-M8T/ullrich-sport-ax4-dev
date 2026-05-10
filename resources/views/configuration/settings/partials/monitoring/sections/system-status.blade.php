<div class="stack stack-lg">
    <div class="grid-auto grid-auto-lg">
        <div class="data-indicator" data-tone="{{ $healthTone }}">
            <span class="data-indicator__label">Gesamtzustand</span>
            <span class="data-indicator__value">{{ $health }}</span>
        </div>
        <div class="data-indicator" data-tone="info">
            <span class="data-indicator__label">Konfiguration</span>
            <span class="data-indicator__value">{{ $configured }} / {{ count($configuration) }}</span>
        </div>
        <div class="data-indicator" data-tone="info">
            <span class="data-indicator__label">Jobs gesamt</span>
            <span class="data-indicator__value">{{ $queueTotal }}</span>
        </div>
        <div class="data-indicator" data-tone="info">
            <span class="data-indicator__label">Log-Verzeichnisse</span>
            <span class="data-indicator__value">{{ count($logDirectories) }}</span>
        </div>
    </div>

    <div>
        <h4 class="h6 mb-2">Log-Verzeichnisse</h4>
        <div class="table-responsive">
            <x-ui.data-table dense>
                <thead>
                <tr>
                    <th scope="col">Pfad</th>
                    <th scope="col">Dateien</th>
                    <th scope="col">Größe</th>
                    <th scope="col">Zuletzt geändert</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logDirectories as $directory)
                    <tr>
                        <td><code>{{ $directory['path'] ?? '-' }}</code></td>
                        <td>{{ $directory['file_count'] ?? 0 }}</td>
                        <td>{{ isset($directory['size_bytes']) ? number_format($directory['size_bytes'] / 1024, 1, ',', '.') . ' KB' : '—' }}</td>
                        <td>
                            {{ isset($directory['last_modified_at']) ? \Illuminate\Support\Carbon::parse($directory['last_modified_at'])->format('d.m.Y H:i') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted text-center py-3">Keine Logverzeichnisse gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </x-ui.data-table>
        </div>
    </div>
</div>
