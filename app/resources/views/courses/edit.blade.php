@extends('layouts.app')
@section('title', 'Edit course')
@section('content')
<a class="back-link" href="{{ route('courses.show', $course) }}">← Back to course</a>
<div class="page-heading"><p class="eyebrow">KEEP LEARNING CLEAR</p><h1>Edit course<span class="accent-dot">.</span></h1><p>{{ $course->title }}</p></div>
<form class="surface editor-surface" method="POST" action="{{ route('courses.update', $course) }}" data-submit>@csrf @method('PUT') @include('courses.form')</form>
@endsection
