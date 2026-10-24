@extends('layouts.app')
@section('title', 'Add a lesson')
@section('content')
<a class="back-link" href="{{ route('courses.show', $course) }}">← {{ $course->title }}</a>
<div class="page-heading"><p class="eyebrow">ONE LESSON, A NEW POSSIBILITY</p><h1>Add a lesson<span class="accent-dot">.</span></h1><p>New lessons are added at the end and start as drafts.</p></div>
<form class="surface editor-surface" action="{{ route('courses.lessons.store', $course) }}" method="POST" data-submit>@csrf @include('lessons.form')</form>
@endsection
