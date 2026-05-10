@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'min' => null,
    'max' => null,
    'maxlength' => null,
    'step' => null,
    'class' => '',
    'colClass' => null,
    'idSuffix' => null,
])

@php
    $fieldId = $idSuffix !== null ? $name . '_' . $idSuffix : $name;
    $errorId = $fieldId . '-error';
    $helpId = $fieldId . '-help';
    $fieldValue = old($name, $value);
    $hasError = $errors->has($name);
    $errorClass = $hasError ? 'is-invalid' : '';
    $inputClass = trim("form-control {$errorClass} {$class}");
    $colClass = $colClass ?? 'col-md-6';
    $describedBy = [];
    if (isset($help)) {
        $describedBy[] = $helpId;
    }
    if ($hasError) {
        $describedBy[] = $errorId;
    }
@endphp

<div class="{{ $colClass }}">
    <label class="form-label" for="{{ $fieldId }}">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    <input
        id="{{ $fieldId }}"
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $fieldValue }}"
        class="{{ $inputClass }}"
        @if($required) required aria-required="true" @endif
        @if($hasError) aria-invalid="true" @endif
        @if(!empty($describedBy)) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($min !== null) min="{{ $min }}" @endif
        @if($max !== null) max="{{ $max }}" @endif
        @if($maxlength !== null) maxlength="{{ $maxlength }}" @endif
        @if($step !== null) step="{{ $step }}" @endif
        {{ $attributes->except(['class', 'colClass']) }}
    >
    @if(isset($help))
        <small id="{{ $helpId }}" class="form-text text-muted">{{ $help }}</small>
    @endif
    @error($name)
        <div id="{{ $errorId }}" class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
