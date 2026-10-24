@extends('layouts.app')
@section('title', 'Teachers')
@section('content')
<div class="page-heading heading-with-action"><div><p class="eyebrow">PEOPLE MAKE THE DIFFERENCE</p><h1>Your teaching team<span class="accent-dot">.</span></h1><p>Create accounts and keep your team’s access up to date.</p></div><a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">Add a teacher <span aria-hidden="true">+</span></a></div>
<section class="surface">
<form class="table-search" method="GET" action="{{ route('admin.teachers.index') }}"><label for="q" class="visually-hidden">Search teachers</label><input class="form-control" id="q" name="q" value="{{ $search }}" placeholder="Search by name or email" maxlength="120"><button class="btn btn-outline-primary" type="submit">Search</button>@if($search !== '')<a href="{{ route('admin.teachers.index') }}" class="btn btn-link">Clear</a>@endif</form>
<x-errors />
<div class="table-responsive" tabindex="0" role="region" aria-label="Teacher accounts — scroll to view all columns"><table class="table academy-table mb-0"><caption class="visually-hidden">Teacher accounts</caption><thead><tr><th scope="col">Teacher</th><th scope="col">Courses</th><th scope="col">Status</th><th scope="col">Account</th></tr></thead><tbody>
@forelse($teachers as $teacher)<tr><td><strong>{{ $teacher->name }}</strong><span class="table-secondary">{{ $teacher->email }}</span></td><td>{{ $teacher->taught_courses_count }}</td><td><span class="status-pill {{ $teacher->is_active ? '' : 'status-muted' }}">{{ $teacher->is_active ? 'Active' : 'Inactive' }}</span></td><td><a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-sm btn-outline-secondary" aria-label="Manage {{ $teacher->name }}">Manage <span aria-hidden="true">↗</span></a></td></tr>
@empty<tr><td colspan="4"><div class="empty-state"><h3>{{ $search !== '' ? 'No matching teachers.' : 'Let’s build your teaching team.' }}</h3><p>{{ $search !== '' ? 'Try a different name or email address.' : 'Add your first teacher to get started.' }}</p></div></td></tr>@endforelse
</tbody></table></div><div class="pagination-wrap">{{ $teachers->links() }}</div>
</section>
@endsection
