@extends('layouts.app')
@section('title', 'Learning overview')
@section('content')
<div class="page-heading"><p class="eyebrow">A LITTLE PROGRESS, EVERY DAY</p><h1>Welcome, {{ auth()->user()->name }}<span class="accent-dot">.</span></h1><p>Your own space to keep learning in view.</p></div>
<div class="row g-3 mb-4"><div class="col-sm-4"><x-stat label="Active courses" :value="$stats['enrollments']" /></div><div class="col-sm-4"><x-stat label="Completed lessons" :value="$stats['completed_lessons']" hint="In your current published courses" /></div><div class="col-sm-4"><x-stat label="Published courses" :value="$stats['available_courses']" hint="Across Penguin Academy" /></div></div>
<div class="row g-3 mb-4"><div class="col-sm-6"><x-stat label="Completed courses" :value="$learningStats['completed_enrollments']" hint="All current published lessons complete" /></div><div class="col-sm-6"><x-stat label="Average course progress" :value="$learningStats['average_progress']" suffix="%" :decimals="2" hint="Across your active, published courses" /></div></div>
<section class="surface"><div class="section-heading"><div><h2>Your learning</h2><p>Your current course enrollments</p></div><a class="text-link" href="{{ route('my-courses') }}">My courses ↗</a></div>
@forelse($enrollments as $enrollment)
<article class="learning-course-row"><div class="d-flex align-items-start gap-3"><span class="course-monogram" aria-hidden="true">{{ mb_substr($enrollment->course->title, 0, 1) }}</span><div><h3 class="h5"><a class="table-name" href="{{ route('courses.show', $enrollment->course) }}">{{ $enrollment->course->title }}</a></h3><p class="small text-secondary mb-0">{{ $enrollment->course->teacher?->name ?? 'Penguin Academy' }}</p></div></div>
<x-learning-progress :progress="$progress[$enrollment->id]" :label="'Progress in '.$enrollment->course->title" />
@if($progress[$enrollment->id]->nextLessonId)<a class="btn btn-primary btn-sm align-self-start" href="{{ route('courses.lessons.show', [$enrollment->course_id, $progress[$enrollment->id]->nextLessonId]) }}" aria-label="Continue learning {{ $enrollment->course->title }}">Continue learning →</a>
@else<a class="btn btn-outline-primary btn-sm align-self-start" href="{{ route('courses.show', $enrollment->course) }}">{{ $progress[$enrollment->id]->isComplete ? 'Review course' : 'View course' }}</a>@endif
</article>
@empty<div class="empty-state spacious"><span class="empty-symbol" aria-hidden="true">▤</span><h3>A new chapter is waiting.</h3><p>You haven’t joined a course yet. Find your first course in the catalog.</p><a class="btn btn-primary btn-sm" href="{{ route('courses.index') }}">Explore courses</a></div>@endforelse
<div class="pagination-wrap">{{ $enrollments->links() }}</div></section>
@endsection
