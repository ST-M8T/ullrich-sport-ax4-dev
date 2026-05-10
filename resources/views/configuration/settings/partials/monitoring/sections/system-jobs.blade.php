<div class="stack stack-lg">
    <div class="grid-auto grid-auto-lg">
        @foreach($statusLabels as $statusKey => $statusLabel)
            <div class="data-indicator" data-tone="{{ $statusKey === 'failed' ? 'warn' : 'info' }}">
                <span class="data-indicator__label">{{ $statusLabel }}</span>
                <span class="data-indicator__value">{{ $counts[$statusKey] ?? 0 }}</span>
            </div>
        @endforeach
    </div>

    <div>
        <h4 class="h6 mb-2">Letzte Jobs</h4>
        <div class="table-responsive">
            <x-ui.data-table dense>
                <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Job</th>
                    <th scope="col">Status</th>
                    <th scope="col">Gestartet</th>
                    <th scope="col">Dauer</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentJobs as $job)
                    <tr>
                        <td>#{{ $job['id'] ?? '—' }}</td>
                        <td>{{ $job['name'] ?? '—' }}</td>
                        <td class="text-uppercase">{{ $job['status'] ?? 'unknown' }}</td>
                        <td>
                            {{ isset($job['started_at']) ? \Illuminate\Support\Carbon::parse($job['started_at'])->format('d.m.Y H:i') : '—' }}
                        </td>
                        <td>
                            {{ isset($job['duration_ms']) ? number_format((int) $job['duration_ms'] / 1000, 2, ',', '.') . ' s' : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">Keine Jobs erfasst.</td>
                    </tr>
                @endforelse
                </tbody>
            </x-ui.data-table>
        </div>
    </div>
</div>
