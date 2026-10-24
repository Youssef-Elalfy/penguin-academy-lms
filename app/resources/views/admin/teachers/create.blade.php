@extends('layouts.app')
@section('title', 'Add a teacher')
@section('content')
<div class="page-heading"><p class="eyebrow">GROW YOUR TEAM</p><h1>A new voice in your academy.</h1><p>Create an account for a member of your teaching team.</p></div>
<div class="surface form-surface"><form method="POST" action="{{ route('admin.teachers.store') }}" data-submit>@csrf @include('admin.teachers.form')</form></div>
@endsection
