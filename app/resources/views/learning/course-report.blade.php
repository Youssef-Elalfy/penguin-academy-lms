@extends('layouts.app')
@section('title', 'Course progress report')
@section('content')
<a class="back-link" href="{{ route('courses.show', $course) }}">← {{ $course->title }}</a>
<div class="page-heading heading-with-action"><div><p class="eyebrow">UNDERSTAND YOUR LEARNERS</p><h1>Course progress<span class="accent-dot">.</span></h1><p>{{ $course->title }} · {{ $metrics['active_students'] }} active student accounts currently enrolled</p></div><a class="btn btn-outline-primary" href="{{ route('courses.enrollments.index', $course) }}">Manage enrollment</a></div>
@include('learning.metrics')
<p class="form-note mb-4">Summary cards cover all current enrollments in this course, independently of the list filters. Draft and archived lessons are excluded. A course with no published lessons has 0% progress.</p>
<x-errors />
<section class="surface">
<form method="GET" action="{{ route('courses.progress.index', $course) }}" class="filter-bar">@include('enrollments.filters')<div class="filter-actions"><button class="btn btn-primary" type="submit">Filter learners</button><a class="btn btn-outline-secondary" href="{{ route('courses.progress.index', $course) }}">Reset</a></div></form>
<div class="table-responsive" tabindex="0" role="region" aria-label="Student progress in {{ $course->title }} — scroll to view all columns"><table class="table academy-table mb-0"><caption class="visually-hidden">Student progress in {{ $course->title }}</caption><thead><tr><th scope="col">Student</th><th scope="col">Enrollment</th><th scope="col">Learning progress</th><th scope="col">Report</th></tr></thead><tbody>
@forelse($enrollments as $enrollment)<tr><td><span class="table-name">{{ $enrollment->student->name }}</span><span class="table-secondary">{{ $enrollment->student->email }}</span>@if(!$enrollment->student->is_active)<span class="table-secondary">Account inactive</span>@endif</td><td><span class="status-pill {{ $enrollment->withdrawn_at ? 'status-muted' : '' }}">{{ $enrollment->withdrawn_at ? 'Withdrawn' : 'Active' }}</span></td><td class="progress-cell"><x-learning-progress :progress="$progress[$enrollment->id]" :label="'Progress for '.$enrollment->student->name" /></td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('courses.progress.show', [$course, $enrollment]) }}" aria-label="View details for {{ $enrollment->student->name }}">View details</a></td></tr>
@empty<tr><td colspan="4"><div class="empty-state"><h3>No learners match.</h3><p>Change the filters or enroll students in this course.</p></div></td></tr>@endforelse
</tbody></table></div><div class="pagination-wrap">{{ $enrollments->links() }}</div></section>
@endsection
