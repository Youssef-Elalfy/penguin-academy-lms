@extends('layouts.app')
@section('title', 'Account security')
@section('content')
<div class="page-heading"><p class="eyebrow">YOUR ACCOUNT</p><h1>A little peace of mind.</h1><p>Keep your account protected with a strong, unique password.</p></div>
<div class="surface form-surface">
    <h2 class="h5 mb-3">Change your password</h2>
    @if(auth()->user()->must_change_password)<div class="alert alert-info" role="status">Choose your own password before opening your workspace. Your temporary password can only be used for this first step.</div>@endif
    <x-errors />
    <form method="POST" action="{{ route('account.password.update') }}" data-submit>
        @csrf @method('PUT')
        <x-input name="current_password" label="Current password" type="password" autocomplete="current-password" required autofocus />
        <x-input name="password" label="New password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required hint="At least 12 characters with uppercase, lowercase, and a number." />
        <x-input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
        <p class="small text-secondary">Updating your password signs out your other sessions.</p>
        <button class="btn btn-primary" type="submit">Update password</button>
    </form>
</div>
@endsection
