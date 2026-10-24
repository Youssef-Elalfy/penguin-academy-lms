@extends('layouts.app')
@section('title', 'Edit lesson')
@section('content')
<a class="back-link" href="{{ route('courses.lessons.show', [$course, $lesson]) }}">← Back to lesson</a>
<div class="page-heading"><p class="eyebrow">{{ $course->title }}</p><h1>Edit lesson<span class="accent-dot">.</span></h1><p>Changes to a published lesson are visible to enrolled students immediately.</p></div>
<form class="surface editor-surface" action="{{ route('courses.lessons.update', [$course, $lesson]) }}" method="POST" data-submit>@csrf @method('PUT') @include('lessons.form')</form>
@endsection
