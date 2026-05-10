@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'required' => false,
    'placeholder' => 'Bitte wählen',
    'class' => '',
    'colClass' => null,
    'idSuffix' => null,
])

@php
    $fieldId = $idSuffix !== null ? $name . '_' . $idSuffix : $name;
    $errorId = $fieldId . '-error';
    $fieldValue = old($name, $value);
    $hasError = $errors->has($name);
    $errorClass = $hasError ? 'is-invalid' : '';
    $selectClass = trim("form-select {$errorClass} {$class}");
    $colClass = $colClass ?? 'col-md-6';
@endphp

<div class="{{ $colClass }}">
    <label class="form-label" for="{{ $fieldId }}">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        class="{{ $selectClass }}"
        @if($required) required aria-required="true" @endif
        @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
        {{ $attributes->except(['class', 'colClass']) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $optionValue => $optionLabel)
            <option
                value="{{ $optionValue }}"
                @selected($fieldValue == $optionValue)
            >
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
    @error($name)
        <div id="{{ $errorId }}" class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
