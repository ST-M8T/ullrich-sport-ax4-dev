@php
    $template = $template ?? null;
    $isEdit = $template !== null;
    $idSuffix = $isEdit ? 'edit_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $template->templateKey()) : 'create';
    $isActive = $template?->isActive() ?? true;
@endphp

<form method="post" action="{{ $action }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    <div class="row g-3">
        <x-forms.input
            name="template_key"
            label="Key"
            type="text"
            :value="$template?->templateKey() ?? ''"
            :required="!$isEdit"
            :readonly="$isEdit"
            col-class="col-12"
            :id-suffix="$idSuffix"
        />

        <x-forms.input
            name="description"
            label="Beschreibung"
            type="text"
            :value="$template?->description() ?? ''"
            col-class="col-12"
            :id-suffix="$idSuffix"
        />

        <x-forms.input
            name="subject"
            label="Betreff"
            type="text"
            :value="$template?->subject() ?? ''"
            :required="true"
            col-class="col-12"
            :id-suffix="$idSuffix"
        />

        <x-forms.textarea
            name="body_html"
            label="HTML Inhalt"
            :value="$template?->bodyHtml() ?? ''"
            :rows="6"
            col-class="col-12"
            :id-suffix="$idSuffix"
        />

        <x-forms.textarea
            name="body_text"
            label="Text Inhalt"
            :value="$template?->bodyText() ?? ''"
            :rows="4"
            col-class="col-12"
            :id-suffix="$idSuffix"
        />

        <x-forms.checkbox
            name="is_active"
            label="Vorlage ist aktiv"
            :checked="$isActive"
            col-class="col-12"
            :id-suffix="$idSuffix"
            :switch="true"
        />

        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? 'Aktualisieren' : 'Speichern' }}</button>
            @if(isset($cancelTarget))
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleRow('{{ str_replace('#', '', $cancelTarget) }}')">Abbrechen</button>
            @endif
        </div>
    </div>
</form>
