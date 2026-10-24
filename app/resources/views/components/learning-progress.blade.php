@props(['progress', 'label' => 'Course progress'])
<div {{ $attributes->class(['learning-progress']) }}>
    <div class="progress-caption"><strong>{{ $progress->percent }}%</strong><span>{{ $progress->completed }} of {{ $progress->total }} lessons complete</span></div>
    <progress value="{{ $progress->percent }}" max="100" aria-label="{{ $label }}">{{ $progress->percent }}%</progress>
    <span class="progress-state">{{ $progress->state }}</span>
</div>
