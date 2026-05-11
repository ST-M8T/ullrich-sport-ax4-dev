{{-- DHL Tracking Timeline Component --}}
{{-- Displays a chronological timeline of tracking events with German labels --}}

@props([
    'events' => [],       // Array of event arrays with: event_code, label, status, description, facility, city, country, occurred_at
    'currentStatus' => null,  // ['code' => string, 'label' => string]
    'isDelivered' => false,
    'trackingNumber' => '',
    'showRefreshButton' => true,
])

@php
    $hasEvents = count($events) > 0;
@endphp

<div class="dhl-tracking-timeline" data-tracking-number="{{ $trackingNumber }}">
    {{-- Current Status Header --}}
    @if($currentStatus)
        <div class="mb-4 p-3 rounded {{ $isDelivered ? 'bg-success bg-opacity-10 border border-success' : 'bg-light border' }}">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small mb-1">Aktueller Status</div>
                    <div class="fw-bold fs-5">{{ $currentStatus['label'] ?? $currentStatus['code'] ?? '—' }}</div>
                    @if($currentStatus['code'])
                        <div class="text-muted small">{{ $currentStatus['code'] }}</div>
                    @endif
                </div>
                @if($showRefreshButton && $trackingNumber)
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        onclick="refreshTrackingTimeline('{{ $trackingNumber }}')"
                        id="refresh-tracking-btn"
                    >
                        <span class="refresh-icon me-1">&#8635;</span>
                        Aktualisieren
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- Timeline --}}
    @if($hasEvents)
        <div class="timeline-wrapper" id="tracking-timeline-wrapper">
            <div class="timeline">
                @foreach($events as $index => $event)
                    <div class="timeline-item mb-3 pb-3 {{ $loop->last ? '' : 'border-bottom' }}"
                         data-event-code="{{ $event['event_code'] ?? '' }}"
                         data-occurred-at="{{ $event['occurred_at'] ?? '' }}"
                    >
                        <div class="d-flex gap-3">
                            {{-- Timeline dot --}}
                            <div class="timeline-dot-wrapper d-flex flex-column align-items-center">
                                <div class="rounded-circle bg-{{ $event['event_code'] === 'MANUAL_SYNC' ? 'secondary' : 'primary' }} bg-opacity-20 border border-{{ $event['event_code'] === 'MANUAL_SYNC' ? 'secondary' : 'primary' }} text-{{ $event['event_code'] === 'MANUAL_SYNC' ? 'secondary' : 'primary' }} d-flex align-items-center justify-content-center" style="width: 12px; height: 12px; min-width: 12px;">
                                </div>
                                @if(!$loop->last)
                                    <div class="vr h-100" style="width: 2px; min-height: 40px;"></div>
                                @endif
                            </div>

                            {{-- Event content --}}
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <span class="fw-semibold me-2">{{ $event['label'] ?? $event['event_code'] ?? '—' }}</span>
                                        @if($isDelivered && $loop->first)
                                            <span class="badge bg-success ms-2">Zugestellt</span>
                                        @elseif($event['event_code'] === 'MANUAL_SYNC')
                                            <span class="badge bg-secondary ms-2">Sync</span>
                                        @endif
                                    </div>
                                    <small class="text-muted text-end">
                                        {{ \Carbon\Carbon::parse($event['occurred_at'])->format('d.m.Y') }}
                                        <br>
                                        {{ \Carbon\Carbon::parse($event['occurred_at'])->format('H:i') }}
                                    </small>
                                </div>

                                @if(($event['description'] ?? '') !== '')
                                    <div class="text-body mb-1">{{ $event['description'] }}</div>
                                @endif

                                @if(($event['facility'] ?? '') || ($event['city'] ?? '') || ($event['country'] ?? ''))
                                    <div class="text-muted small">
                                        <span class="me-1">&#128205;</span>
                                        {{ $event['facility'] ?? '' }}
                                        @if($event['city'])
                                            {{ $event['city'] }}
                                        @endif
                                        @if($event['country'])
                                            {{ $event['country'] }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Refresh script --}}
        @if($showRefreshButton && $trackingNumber)
            <script>
                function refreshTrackingTimeline(trackingNumber) {
                    const btn = document.getElementById('refresh-tracking-btn');
                    const wrapper = document.getElementById('tracking-timeline-wrapper');

                    if (!btn || !wrapper) return;

                    // Disable button and show loading state
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Lädt...';

                    const url = `/api/admin/dhl/tracking/${encodeURIComponent(trackingNumber)}/events`;

                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.events) {
                                // Rebuild timeline HTML
                                let html = '<div class="timeline">';
                                data.events.forEach((event, index) => {
                                    const isFirst = index === 0;
                                    const isManualSync = event.event_code === 'MANUAL_SYNC';
                                    const isDelivered = data.is_delivered && isFirst;

                                    html += `
                                        <div class="timeline-item mb-3 pb-3 ${!isFirst ? 'border-bottom' : ''}"
                                             data-event-code="${event.event_code || ''}"
                                             data-occurred-at="${event.occurred_at || ''}">
                                            <div class="d-flex gap-3">
                                                <div class="timeline-dot-wrapper d-flex flex-column align-items-center">
                                                    <div class="rounded-circle bg-${isManualSync ? 'secondary' : 'primary'} bg-opacity-20 border border-${isManualSync ? 'secondary' : 'primary'} text-${isManualSync ? 'secondary' : 'primary'} d-flex align-items-center justify-content-center" style="width: 12px; height: 12px; min-width: 12px;"></div>
                                                    ${!isFirst ? '<div class="vr h-100" style="width: 2px; min-height: 40px;"></div>' : ''}
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <div>
                                                            <span class="fw-semibold me-2">${event.label || event.event_code || '—'}</span>
                                                            ${isDelivered ? '<span class="badge bg-success ms-2">Zugestellt</span>' : ''}
                                                            ${isManualSync ? '<span class="badge bg-secondary ms-2">Sync</span>' : ''}
                                                        </div>
                                                        <small class="text-muted text-end">
                                                            ${formatDate(event.occurred_at)}
                                                            <br>
                                                            ${formatTime(event.occurred_at)}
                                                        </small>
                                                    </div>
                                                    ${event.description ? `<div class="text-body mb-1">${event.description}</div>` : ''}
                                                    ${(event.facility || event.city || event.country) ? `<div class="text-muted small"><span class="me-1">&#128205;</span>${event.facility || ''} ${event.city || ''} ${event.country || ''}</div>` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                });
                                html += '</div>';
                                wrapper.innerHTML = html;

                                // Update current status
                                const statusDiv = document.querySelector('.dhl-tracking-timeline > .mb-4');
                                if (statusDiv && data.current_status) {
                                    statusDiv.querySelector('.fw-bold').textContent = data.current_status.label || data.current_status.code;
                                    statusDiv.querySelector('.text-muted.small').textContent = data.current_status.code || '';
                                }
                            } else {
                                alert(data.error || 'Fehler beim Laden der Tracking-Daten');
                            }
                        })
                        .catch(error => {
                            console.error('Error refreshing tracking:', error);
                            alert('Fehler bei der Verbindung');
                        })
                        .finally(() => {
                            // Re-enable button
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = '<span class="refresh-icon me-1">&#8635;</span> Aktualisieren';
                            }
                        });
                }

                function formatDate(dateStr) {
                    if (!dateStr) return '';
                    const date = new Date(dateStr);
                    return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
                }

                function formatTime(dateStr) {
                    if (!dateStr) return '';
                    const date = new Date(dateStr);
                    return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                }
            </script>
        @endif
    @else
        <div class="text-center text-muted py-4">
            <div class="mb-2">&#128233;</div>
            <div>Keine Sendungsverfolgung-Events vorhanden.</div>
            <div class="small">Events werden nach der ersten Synchronisierung angezeigt.</div>
        </div>
    @endif
</div>