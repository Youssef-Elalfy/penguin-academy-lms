@extends('layouts.app')
@section('title', 'Manage teacher')
@section('content')
<div class="page-heading"><p class="eyebrow">TEACHER ACCOUNT</p><h1>{{ $teacher->name }}</h1><p>Manage account details, password, and access.</p></div>
<div class="surface form-surface"><form method="POST" action="{{ route('admin.teachers.update', $teacher) }}" data-submit>@csrf @method('PUT') @include('admin.teachers.form')</form></div>
@endsection
