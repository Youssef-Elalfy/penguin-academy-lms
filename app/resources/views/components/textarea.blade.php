@props(['name', 'label', 'value' => '', 'hint' => null, 'rows' => 5])
<div class="mb-3">
<label for="{{ $name }}" class="form-label">{{ $label }}</label>
<textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }} @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @elseif($hint) aria-describedby="{{ $name }}-hint" @endif>{{ old($name, $value) }}</textarea>
@error($name)<div class="invalid-feedback" id="{{ $name }}-error">{{ $message }}</div>@enderror
@if($hint)<div class="form-text" id="{{ $name }}-hint">{{ $hint }}</div>@endif
</div>
