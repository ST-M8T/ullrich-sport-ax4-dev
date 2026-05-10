@php
    $user = $user ?? null;
    $isEdit = $user !== null;
    $idSuffix = $isEdit ? 'edit_' . $user->id()->toInt() : 'create';

    $rolesForSelect = [];
    foreach ($roleOptions as $roleKey => $roleMeta) {
        $rolesForSelect[$roleKey] = $roleMeta['label'] ?? $roleKey;
    }
@endphp

<form method="post" action="{{ $action }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    <div class="row g-3">
        @if(!$isEdit)
            <x-forms.input
                name="username"
                label="Username"
                type="text"
                :value="old('username')"
                :required="true"
                col-class="col-md-6"
                :id-suffix="$idSuffix"
            />

            <x-forms.select
                name="role"
                label="Rolle"
                :options="$rolesForSelect"
                :value="old('role')"
                :required="true"
                :placeholder="false"
                col-class="col-md-6"
                :id-suffix="$idSuffix"
            />

            <x-forms.input
                name="password"
                label="Passwort"
                type="password"
                :required="true"
                col-class="col-md-6"
                :id-suffix="$idSuffix"
            />

            <x-forms.input
                name="password_confirmation"
                label="Passwort bestätigen"
                type="password"
                :required="true"
                col-class="col-md-6"
                :id-suffix="$idSuffix"
            />
        @else
            <div class="col-md-6">
                <label class="form-label" for="username_display_{{ $idSuffix }}">Username</label>
                <input
                    id="username_display_{{ $idSuffix }}"
                    type="text"
                    value="{{ $user->username() }}"
                    class="form-control bg-light"
                    readonly
                    aria-readonly="true"
                    aria-describedby="username_display_{{ $idSuffix }}-help"
                >
                <small id="username_display_{{ $idSuffix }}-help" class="text-muted">Username kann nicht geändert werden.</small>
            </div>

            <x-forms.select
                name="role"
                label="Rolle"
                :options="$rolesForSelect"
                :value="$user->role()"
                :required="true"
                :placeholder="false"
                col-class="col-md-6"
                :id-suffix="$idSuffix"
            />
        @endif

        <x-forms.input
            name="display_name"
            label="Anzeigename"
            type="text"
            :value="old('display_name', $user?->displayName() ?? '')"
            col-class="col-md-6"
            :id-suffix="$idSuffix"
        />

        <x-forms.input
            name="email"
            label="E-Mail"
            type="email"
            :value="old('email', $user?->email() ?? '')"
            col-class="col-md-6"
            :id-suffix="$idSuffix"
        />

        @if($isEdit)
            <x-forms.input
                name="password"
                label="Neues Passwort (leer lassen, um zu behalten)"
                type="password"
                col-class="col-md-6"
                :id-suffix="$idSuffix"
            />

            <x-forms.input
                name="password_confirmation"
                label="Passwort bestätigen"
                type="password"
                col-class="col-md-6"
                :id-suffix="$idSuffix"
            />
        @endif

        <x-forms.checkbox
            name="must_change_password"
            label="Passwortwechsel bei nächstem Login erforderlich"
            :checked="(bool) old('must_change_password', $user?->requiresPasswordChange() ?? true)"
            col-class="col-12"
            :id-suffix="$idSuffix"
            :switch="true"
        />

        <x-forms.checkbox
            name="disabled"
            label="Benutzer deaktiviert"
            :checked="(bool) old('disabled', $user?->disabled() ?? false)"
            col-class="col-12"
            :id-suffix="$idSuffix"
            :switch="true"
        />

        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? 'Aktualisieren' : 'Benutzer erstellen' }}</button>
            @if(isset($cancelTarget))
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleRow('{{ str_replace('#', '', $cancelTarget) }}')">Abbrechen</button>
            @endif
        </div>
    </div>
</form>
