@extends('layouts.app')
@section('title', 'Teaching overview')
@section('content')
<div class="page-heading heading-with-action"><div><p class="eyebrow">YOUR TEACHING SPACE</p><h1>Hello, {{ auth()->user()->name }}<span class="accent-dot">.</span></h1><p>Stay connected to your courses and the students learning with you.</p></div><a class="btn btn-primary" href="{{ route('courses.create') }}">Create a course +</a></div>
<div class="row g-3 mb-4"><div class="col-sm-4"><x-stat label="Assigned courses" :value="$stats['courses']" /></div><div class="col-sm-4"><x-stat label="Lessons" :value="$stats['lessons']" hint="Current draft and published lessons" /></div><div class="col-sm-4"><x-stat label="Active students" :value="$learningStats['active_students']" hint="Distinct active accounts currently enrolled" /></div></div>
@include('learning.metrics', ['metrics' => $learningStats])
@include('learning.course-table')
<p class="form-note">Your reports cover only your assigned courses. Current enrollment counts follow withdrawal status; inactive student accounts are identified individually in the roster.</p>
@endsection
