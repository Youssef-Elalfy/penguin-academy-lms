@extends('layouts.app')
@section('title', 'Create a course')
@section('content')
<a class="back-link" href="{{ route('courses.index') }}">← All courses</a>
<div class="page-heading"><p class="eyebrow">FROM AN IDEA TO A NEW CHAPTER</p><h1>Create a course<span class="accent-dot">.</span></h1><p>Start with the essentials. Add lessons and publish when you are ready.</p></div>
<form class="surface editor-surface" method="POST" action="{{ route('courses.store') }}" data-submit>@csrf @include('courses.form')</form>
@endsection
