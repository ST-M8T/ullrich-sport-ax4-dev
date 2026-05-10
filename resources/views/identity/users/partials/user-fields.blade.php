@php
    /** @var \App\Domain\Identity\User|null $user */
    $user = $user ?? null;
    $roleOptions = [];
    if (isset($availableRoles) && is_iterable($availableRoles)) {
        foreach ($availableRoles as $roleDefinition) {
            if (!is_array($roleDefinition) || empty($roleDefinition['value'])) {
                continue;
            }

            $value = strtolower(trim((string) $roleDefinition['value']));
            $roleOptions[$value] = strtoupper($value) . ' — ' . ($roleDefinition['label'] ?? \Illuminate\Support\Str::headline($value));
        }
    }
    $selectedRole = strtolower(trim((string) old('role', $user?->role() ?? array_key_first($roleOptions) ?? '')));
@endphp

<div class="col-md-4">
    <x-forms.input
        name="username"
        label="Username"
        type="text"
        :value="old('username', $user?->username() ?? '')"
        required
        autocomplete="off"
    />
    <small class="form-text text-muted">Nur Kleinbuchstaben, Zahlen und Bindestriche empfohlen.</small>
</div>

<x-forms.input
    name="display_name"
    label="Anzeigename"
    type="text"
    :value="old('display_name', $user?->displayName() ?? '')"
    autocomplete="off"
    col-class="col-md-4"
/>

<x-forms.input
    name="email"
    label="E-Mail"
    type="email"
    :value="old('email', $user?->email() ?? '')"
    autocomplete="off"
    col-class="col-md-4"
/>

<x-forms.select
    name="role"
    label="Rolle"
    :options="$roleOptions"
    :value="$selectedRole"
    required
    class="text-uppercase"
    col-class="col-md-4"
/>
