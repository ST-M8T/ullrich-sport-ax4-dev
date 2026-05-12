@if(empty($groups))
    <x-ui.empty-state
        title="Keine Gruppen"
        description="Es sind keine konfigurierten Gruppen vorhanden."
    />
@else
    @php
        $groupTabsArray = collect($groupTabs)->mapWithKeys(function ($tab, $key) {
            return [$key => $tab['label'] ?? $key];
        })->all();
    @endphp

    <div class="mb-3">
        <h2 class="h5 mb-1">Konfiguration</h2>
        <p class="text-muted mb-0 small">Systemeinstellungen nach Gruppen organisiert.</p>
    </div>

    <x-tabs
        :tabs="$groupTabsArray"
        :active-tab="$activeGroup"
        :base-url="request()->url()"
        :tab-param="$groupTabParam"
        aria-label="Konfigurationsgruppen"
        class="mb-4"
    />

    @foreach($processedGroups as $slug => $group)
        @if($activeGroup === $slug)
            @if(!empty($group['redirect_to']))
                @php $redirect = $group['redirect_to']; @endphp
                <section class="card shadow-sm mb-4">
                    <div class="card-header">
                        <div>
                            <h2 class="h5 mb-0">{{ $group['label'] }}</h2>
                            <p class="text-muted mb-0 small">{{ $group['description'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                            <div>
                                {{ $redirect['description'] ?? 'Diese Einstellungen wurden konsolidiert.' }}
                            </div>
                            <a href="{{ route($redirect['route']) }}" class="btn btn-primary btn-sm">
                                {{ $redirect['label'] ?? 'Zur neuen Seite' }}
                            </a>
                        </div>
                    </div>
                </section>
                @continue
            @endif
            <section class="card shadow-sm mb-4">
                <div class="card-header">
                    <div>
                        <h2 class="h5 mb-0">{{ $group['label'] }}</h2>
                        <p class="text-muted mb-0 small">{{ $group['description'] ?? '' }}</p>
                    </div>
                </div>
                <form method="post" action="{{ route('configuration-settings.group-update', $slug) }}">
                    @csrf
                    <div class="card-body">
                        <div class="row g-4">
                            @foreach($group['fields'] as $field)
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="{{ $field['fieldKey'] }}">
                                        {{ $field['label'] }}
                                    </label>

                                    @if($field['type'] === 'textarea')
                                        <textarea
                                            class="form-control"
                                            name="{{ $field['fieldKey'] }}"
                                            id="{{ $field['fieldKey'] }}"
                                            rows="4"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        >{{ $field['isSecret'] ? '' : $field['current'] }}</textarea>
                                    @elseif($field['type'] === 'select')
                                        <select
                                            class="form-select"
                                            name="{{ $field['fieldKey'] }}"
                                            id="{{ $field['fieldKey'] }}"
                                        >
                                            <option value="">— Auswahl —</option>
                                            @foreach(($field['options'] ?? []) as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}" @selected((string) $field['current'] === (string) $optionValue)>
                                                    {{ $optionLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field['type'] === 'checkbox')
                                        <div class="form-check mt-1">
                                            <input type="hidden" name="{{ $field['fieldKey'] }}" value="0">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                id="{{ $field['fieldKey'] }}"
                                                name="{{ $field['fieldKey'] }}"
                                                value="1"
                                                @checked($field['current'])
                                            >
                                            <label class="form-check-label" for="{{ $field['fieldKey'] }}">
                                                {{ $field['checkbox_label'] ?? 'Aktiviert' }}
                                            </label>
                                        </div>
                                    @elseif($field['type'] === 'number')
                                        <input
                                            type="number"
                                            class="form-control"
                                            name="{{ $field['fieldKey'] }}"
                                            id="{{ $field['fieldKey'] }}"
                                            value="{{ $field['current'] }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        >
                                    @elseif($field['type'] === 'email')
                                        <input
                                            type="email"
                                            class="form-control"
                                            name="{{ $field['fieldKey'] }}"
                                            id="{{ $field['fieldKey'] }}"
                                            value="{{ $field['current'] }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        >
                                    @elseif($field['type'] === 'password')
                                        <input
                                            type="password"
                                            class="form-control"
                                            name="{{ $field['fieldKey'] }}"
                                            id="{{ $field['fieldKey'] }}"
                                            value=""
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                            autocomplete="new-password"
                                        >
                                        @if($field['entry'] && $field['entry']->rawValue())
                                            <small class="text-muted d-block mt-1">
                                                Aktuell gesetzt – leer lassen, um den Wert zu behalten.
                                            </small>
                                        @endif
                                    @else
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="{{ $field['fieldKey'] }}"
                                            id="{{ $field['fieldKey'] }}"
                                            value="{{ $field['isSecret'] ? '' : $field['current'] }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        >
                                    @endif

                                    @if(!empty($field['help']))
                                        <small class="text-muted d-block mt-1">{{ $field['help'] }}</small>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if($slug === 'mail')
                            <div class="mt-4 pt-4 border-top">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h5 class="h6 mb-1">Mail-Vorlagen</h5>
                                        <p class="text-muted mb-0 small">Transactional Templates für DHL & Benachrichtigungen verwalten.</p>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="toggleCreateForm('mailTemplateCreateForm')">
                                        <span id="mailTemplateCreateFormToggle">Neue Vorlage anlegen</span>
                                    </button>
                                </div>

                                <div id="mailTemplateCreateForm" class="mb-4" style="display: none;">
                                    <div class="card card-body bg-light">
                                        <h6 class="mb-3">Neue Mail-Vorlage anlegen</h6>
                                        @include('configuration.settings.partials.mail-template-form', [
                                            'action' => route('configuration-mail-templates.store'),
                                            'method' => 'POST',
                                            'template' => null,
                                            'cancelTarget' => '#mailTemplateCreateForm',
                                        ])
                                    </div>
                                </div>

                                @if(empty($mailTemplates))
                                    <div class="alert alert-info mb-0">
                                        <small>Noch keine Mail-Vorlagen vorhanden.</small>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th scope="col">Key</th>
                                                    <th scope="col">Beschreibung</th>
                                                    <th scope="col">Betreff</th>
                                                    <th scope="col">Status</th>
                                                    <th scope="col">Erstellt</th>
                                                    <th scope="col">Aktualisiert</th>
                                                    <th scope="col" class="text-end">Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($mailTemplates as $template)
                                                    <tr data-template-key="{{ $template->templateKey() }}">
                                                        <td><code class="small">{{ $template->templateKey() }}</code></td>
                                                        <td>{{ $template->description() ?? '—' }}</td>
                                                        <td>{{ Str::limit($template->subject(), 50) }}</td>
                                                        <td>
                                                            @if($template->isActive())
                                                                <span class="badge bg-success">Aktiv</span>
                                                            @else
                                                                <span class="badge bg-secondary">Inaktiv</span>
                                                            @endif
                                                        </td>
                                                        <td class="small text-muted">{{ $template->createdAt()->format('d.m.Y H:i') }}</td>
                                                        <td class="small text-muted">{{ $template->updatedAt()->format('d.m.Y H:i') }}</td>
                                                        <td class="text-end">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button type="button" class="btn btn-outline-info btn-sm" onclick="toggleRow('templatePreview{{ $loop->index }}')">
                                                                    Preview
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleRow('templateEdit{{ $loop->index }}')">
                                                                    Bearbeiten
                                                                </button>
                                                                <form method="post" action="{{ route('configuration-mail-templates.destroy', ['templateKey' => $template->templateKey()]) }}" class="d-inline" onsubmit="return confirm('Vorlage wirklich löschen?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Löschen</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr id="templatePreview{{ $loop->index }}" style="display: none;">
                                                        <td colspan="7" class="bg-light">
                                                            <div class="p-3">
                                                                <h6 class="mb-3">Vorschau: {{ $template->templateKey() }}</h6>
                                                                <dl class="row mb-3">
                                                                    <dt class="col-sm-3">Key</dt>
                                                                    <dd class="col-sm-9"><code>{{ $template->templateKey() }}</code></dd>
                                                                    <dt class="col-sm-3">Beschreibung</dt>
                                                                    <dd class="col-sm-9">{{ $template->description() ?? '—' }}</dd>
                                                                    <dt class="col-sm-3">Betreff</dt>
                                                                    <dd class="col-sm-9">{{ $template->subject() }}</dd>
                                                                    <dt class="col-sm-3">Status</dt>
                                                                    <dd class="col-sm-9">
                                                                        @if($template->isActive())
                                                                            <span class="badge bg-success">Aktiv</span>
                                                                        @else
                                                                            <span class="badge bg-secondary">Inaktiv</span>
                                                                        @endif
                                                                    </dd>
                                                                </dl>
                                                                @if($template->bodyHtml())
                                                                    <h6>HTML Vorschau</h6>
                                                                    <div class="border rounded p-3 mb-3 bg-white">
                                                                        {!! $template->bodyHtml() !!}
                                                                    </div>
                                                                @endif
                                                                @if($template->bodyText())
                                                                    <h6>Text Variante</h6>
                                                                    <pre class="border rounded p-3 bg-light mb-0">{{ $template->bodyText() }}</pre>
                                                                @endif
                                                                @if(!$template->bodyHtml() && !$template->bodyText())
                                                                    <div class="alert alert-warning mb-0">Keine Inhalte hinterlegt.</div>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr id="templateEdit{{ $loop->index }}" style="display: none;">
                                                        <td colspan="7" class="bg-light">
                                                            <div class="p-3">
                                                                <h6 class="mb-3">Mail-Vorlage bearbeiten: {{ $template->templateKey() }}</h6>
                                                                @include('configuration.settings.partials.mail-template-form', [
                                                                    'action' => route('configuration-mail-templates.update', ['templateKey' => $template->templateKey()]),
                                                                    'method' => 'PUT',
                                                                    'template' => $template,
                                                                    'cancelTarget' => '#templateEdit{{ $loop->index }}',
                                                                ])
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary">
                            {{ $group['submit_label'] ?? 'Speichern' }}
                        </button>
                    </div>
                </form>
            </section>
        @endif
    @endforeach
@endif

