@extends('layouts.app')
@section('title', $student ? 'Course catalog' : 'Course management')
@section('content')
<div class="page-heading heading-with-action"><div><p class="eyebrow">{{ $student ? 'KEEP YOUR CURIOSITY MOVING' : 'YOUR ACADEMY, IN FOCUS' }}</p><h1>{{ $student ? 'Find your next chapter' : 'Course management' }}<span class="accent-dot">.</span></h1><p>{{ $student ? 'Explore published courses and make space for something new.' : 'Shape the lessons, connect your students, and bring learning to life.' }}</p></div>
@can('create', \App\Models\Course::class)<a class="btn btn-primary" href="{{ route('courses.create') }}">Create a course <span aria-hidden="true">+</span></a>@endcan</div>
<x-errors />
<section class="surface mb-4">
<form method="GET" action="{{ route('courses.index') }}" class="filter-bar">
<div class="filter-search"><label for="q" class="form-label">Search courses</label><input id="q" name="q" class="form-control" placeholder="A title or topic…" value="{{ $filters['q'] ?? '' }}" maxlength="120"></div>
@if(!$student)<div><label for="status" class="form-label">Publication</label><select id="status" name="status" class="form-select"><option value="">All courses</option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft / scheduled</option></select></div>@endif
@if(auth()->user()->hasRole(\App\Enums\UserRole::Admin))<div><label for="teacher_id" class="form-label">Teacher</label><select id="teacher_id" name="teacher_id" class="form-select"><option value="">All teachers</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(($filters['teacher_id'] ?? '') == $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>@endif
<div><label for="sort" class="form-label">Sort by</label><select name="sort" id="sort" class="form-select"><option value="newest" @selected(($filters['sort'] ?? '') !== 'title')>Newest first</option><option value="title" @selected(($filters['sort'] ?? '') === 'title')>Title A–Z</option></select></div>
<div class="filter-actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="{{ route('courses.index') }}">Reset</a></div>
</form></section>
<div class="list-caption"><span>{{ $courses->total() }} {{ Str::plural('course', $courses->total()) }}</span><span>{{ $student ? 'Made for curious minds' : 'Your permitted courses' }}</span></div>
@if($student)
<div class="row g-4">@forelse($courses as $course)<div class="col-md-6 col-xl-4"><article class="surface catalog-card h-100"><div class="catalog-accent"><span class="course-monogram" aria-hidden="true">{{ mb_substr($course->title, 0, 1) }}</span>@if($course->is_enrolled)<span class="status-pill">Enrolled</span>@endif</div><div class="catalog-body"><p class="eyebrow mb-2">{{ $course->teacher?->name ?? 'Penguin Academy' }}</p><h2><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></h2><p class="catalog-summary">{{ $course->summary ?: 'Discover the lessons and learning opportunities in this course.' }}</p><div class="catalog-meta"><span>{{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</span><a href="{{ route('courses.show', $course) }}" class="text-link">View course <span aria-hidden="true">↗</span></a></div></div></article></div>
@empty<div class="col-12"><div class="surface empty-state spacious"><span class="empty-symbol" aria-hidden="true">▤</span><h2>No courses found.</h2><p>Try another search, or check back when your academy publishes new courses.</p></div></div>@endforelse</div>
@else
<section class="surface"><div class="table-responsive" tabindex="0" role="region" aria-label="Course management results — scroll to view all columns"><table class="table academy-table mb-0"><caption class="visually-hidden">Course management results</caption><thead><tr><th scope="col">Course</th><th scope="col">Teacher</th><th scope="col">Lessons</th><th scope="col">Enrolled</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
@forelse($courses as $course)<tr><td><a class="table-name" href="{{ route('courses.show', $course) }}">{{ $course->title }}</a><span class="table-secondary">{{ $course->slug }}</span></td><td>{{ $course->teacher?->name ?? 'Unassigned' }}</td><td>{{ $course->lessons_count }}</td><td>{{ $course->enrollments_count }}</td><td><span class="status-pill {{ $course->isPublished() ? '' : 'status-muted' }}">{{ $course->isPublished() ? 'Published' : 'Draft / scheduled' }}</span></td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('courses.show', $course) }}" aria-label="Manage {{ $course->title }}">Manage</a></td></tr>
@empty<tr><td colspan="6"><div class="empty-state spacious"><h2>Your next course starts here.</h2><p>No courses match these filters. Create a course or adjust your search.</p><a href="{{ route('courses.create') }}" class="btn btn-primary btn-sm">Create a course</a></div></td></tr>@endforelse
</tbody></table></div></section>
@endif
<div class="pagination-wrap">{{ $courses->links() }}</div>
@endsection
