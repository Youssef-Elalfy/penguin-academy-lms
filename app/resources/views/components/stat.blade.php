@props(['label', 'value', 'hint' => null, 'suffix' => '', 'decimals' => 0])
<div {{ $attributes->class(['stat-card h-100']) }}>
    <p class="stat-label">{{ $label }}</p>
    <p class="stat-value">{{ number_format($value, $decimals) }}{{ $suffix }}</p>
    @if($hint)<p class="stat-hint">{{ $hint }}</p>@endif
</div>
