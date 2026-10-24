@extends('layouts.app')
@section('title', 'Student progress report')
@section('content')
<a class="back-link" href="{{ route('courses.progress.index', $course) }}">← Course progress</a>
<div class="page-heading"><p class="eyebrow">ONE LEARNER, EVERY STEP</p><h1>{{ $enrollment->student->name }}</h1><p>{{ $enrollment->student->email }} · {{ $course->title }}</p></div>
<section class="surface p-4 mb-4"><div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><h2 class="h5 mb-0">Learning overview</h2><span class="status-pill {{ $enrollment->withdrawn_at ? 'status-muted' : '' }}">{{ $enrollment->withdrawn_at ? 'Withdrawn — history retained' : 'Active enrollment' }}</span></div><x-learning-progress :progress="$progress" :label="'Progress for '.$enrollment->student->name" /><p class="form-note mb-0 mt-3">Only currently published, non-archived lessons appear below. Completion history for other lessons is retained. Withdrawing revokes learning access.</p></section>
<section class="surface"><div class="table-responsive" tabindex="0" role="region" aria-label="Published lesson progress for {{ $enrollment->student->name }} — scroll to view all columns"><table class="table academy-table mb-0"><caption class="visually-hidden">Published lesson progress for {{ $enrollment->student->name }}</caption><thead><tr><th scope="col">Lesson</th><th scope="col">Progress</th><th scope="col">Completed at (UTC)</th></tr></thead><tbody>
@forelse($lessons as $lesson)<tr><td><a class="table-name" href="{{ route('courses.lessons.show', [$course, $lesson]) }}">{{ $lesson->title }}</a></td><td><span class="status-pill {{ $lesson->completed_at ? '' : 'status-muted' }}">{{ $lesson->completed_at ? 'Completed' : 'Incomplete' }}</span></td><td>{{ $lesson->completed_at ?? 'Not completed' }}</td></tr>
@empty<tr><td colspan="3"><div class="empty-state"><h3>No published lessons yet.</h3><p>Progress is 0% until this course has eligible lessons.</p></div></td></tr>@endforelse
</tbody></table></div><div class="pagination-wrap">{{ $lessons->links() }}</div></section>
@endsection
