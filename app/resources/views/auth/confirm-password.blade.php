@extends('layouts.app')
@section('title', 'Confirm your password')
@section('content')
<div class="page-heading"><p class="eyebrow">ACCOUNT SECURITY</p><h1>Confirm it’s you.</h1><p>Enter your password to continue.</p></div>
<div class="surface form-surface"><x-errors /><form method="POST" action="{{ route('password.confirm.store') }}" data-submit>@csrf
<x-input name="password" label="Current password" type="password" autocomplete="current-password" required autofocus />
<button class="btn btn-primary" type="submit">Confirm password</button></form></div>
@endsection
