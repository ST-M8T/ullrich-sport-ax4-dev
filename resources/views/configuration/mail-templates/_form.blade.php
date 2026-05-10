@php
    use App\View\Helpers\DomainFormHelper;

    /** @var \App\Domain\Configuration\MailTemplate|null $template */
    $template = $template ?? null;
    $isEdit = $template !== null;
    $action = $action ?? '';
    $method = $method ?? 'POST';
    $submitLabel = $submitLabel ?? 'Speichern';

    $value = fn(string $field, $fallback = null) => DomainFormHelper::value($field, $fallback, $template);
@endphp

<x-forms.form :action="$action" :method="$method === 'POST' ? null : $method" :novalidate="false">
    <x-forms.input
        name="template_key"
        label="Key"
        type="text"
        :value="$value('template_key')"
        :required="true"
        :readonly="$isEdit"
        col-class="col-12"
    />
    <x-forms.input
        name="description"
        label="Beschreibung"
        type="text"
        :value="$value('description')"
        col-class="col-12"
    />
    <x-forms.input
        name="subject"
        label="Betreff"
        type="text"
        :value="$value('subject')"
        :required="true"
        col-class="col-12"
    />
    <x-forms.textarea
        name="body_html"
        label="HTML Inhalt"
        :value="$value('body_html')"
        :rows="8"
    />
    <x-forms.textarea
        name="body_text"
        label="Text Inhalt"
        :value="$value('body_text')"
        :rows="6"
    />
    <div class="col-12">
        <div class="form-check form-switch">
            <input
                class="form-check-input @error('is_active') is-invalid @enderror"
                type="checkbox"
                role="switch"
                id="is_active"
                name="is_active"
                value="1"
                @checked($value('is_active', true))
            >
            <label class="form-check-label" for="is_active">Vorlage ist aktiv</label>
            @error('is_active')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('configuration-mail-templates')"
        />
    </x-slot>
</x-forms.form>
