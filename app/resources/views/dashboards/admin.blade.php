@extends('layouts.app')
@section('title', 'Academy overview')
@section('content')
<div class="page-heading heading-with-action"><div><p class="eyebrow">THE BIG PICTURE</p><h1>Academy overview<span class="accent-dot">.</span></h1><p>A clear view of the people and learning across your academy.</p></div><a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">Add a teacher <span aria-hidden="true">+</span></a></div>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><x-stat label="Active teachers" :value="$stats['teachers']" hint="Your teaching team" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Active students" :value="$stats['students']" hint="Learning together" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Courses" :value="$stats['courses']" hint="Published and draft" /></div>
    <div class="col-6 col-xl-3"><x-stat label="Current enrollments" :value="$stats['enrollments']" hint="Across available records" /></div>
</div>
<div class="row g-3 mb-4"><div class="col-sm-6"><x-stat label="Published courses" :value="$learningStats['published_courses']" /></div><div class="col-sm-6"><x-stat label="Draft / scheduled courses" :value="$learningStats['draft_courses']" /></div></div>
@include('learning.metrics', ['metrics' => $learningStats])
@include('learning.course-table')
<div class="row g-4">
<div class="col-xl-7"><section class="surface">
    <div class="section-heading"><div><h2>Your teaching team</h2><p>Recently added teacher accounts</p></div><a href="{{ route('admin.teachers.index') }}" class="text-link">View all <span aria-hidden="true">↗</span></a></div>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Recent teachers — scroll to view all columns"><table class="table academy-table mb-0"><caption class="visually-hidden">Recent teachers</caption><thead><tr><th scope="col">Teacher</th><th scope="col">Courses</th><th scope="col">Status</th></tr></thead><tbody>
    @forelse($teachers as $teacher)<tr><td><a class="table-name" href="{{ route('admin.teachers.edit', $teacher) }}">{{ $teacher->name }}</a><span class="table-secondary">{{ $teacher->email }}</span></td><td>{{ $teacher->taught_courses_count }}</td><td><span class="status-pill {{ $teacher->is_active ? '' : 'status-muted' }}">{{ $teacher->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
    @empty<tr><td colspan="3"><div class="empty-state"><span class="empty-symbol" aria-hidden="true">♧</span><h3>Good teaching starts with people.</h3><p>Add your first teacher to build your academy team.</p><a href="{{ route('admin.teachers.create') }}" class="btn btn-outline-primary btn-sm">Add a teacher</a></div></td></tr>@endforelse
    </tbody></table></div>
</section></div>
<div class="col-xl-5"><section class="surface">
    <div class="section-heading"><div><h2>New to the academy</h2><p>Your most recent students</p></div></div>
    <div class="people-list">@forelse($students as $student)
    <div class="person-row"><span class="avatar avatar-light">{{ mb_substr($student->name, 0, 1) }}</span><div class="min-width-zero"><strong>{{ $student->name }}</strong><span>{{ $student->email }}</span></div><span class="small text-secondary ms-auto">{{ $student->created_at->format('M j') }}</span></div>
    @empty<div class="empty-state"><h3>A new community starts here.</h3><p>Students will appear here when they create an account.</p></div>@endforelse</div>
</section></div>
</div>
@endsection
