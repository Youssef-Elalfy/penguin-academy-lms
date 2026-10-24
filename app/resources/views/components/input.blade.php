@props(['name', 'label', 'type' => 'text', 'value' => '', 'hint' => null])
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
        @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error"
        @elseif($hint) aria-describedby="{{ $name }}-hint" @endif>
    @error($name)<div class="invalid-feedback" id="{{ $name }}-error">{{ $message }}</div>@enderror
    @if($hint)<div class="form-text" id="{{ $name }}-hint">{{ $hint }}</div>@endif
</div>
