@extends('layouts.guest')
@section('title', 'Create an account')
@section('content')
<p class="eyebrow">LET CURIOSITY LEAD THE WAY</p>
<h1 class="auth-heading">Start your journey.</h1>
<p class="text-secondary mb-4">Create your student account to join Penguin Academy.</p>
<x-errors />
<form method="POST" action="{{ route('register.store') }}" data-submit>
    @csrf
    <x-input name="name" label="Full name" autocomplete="name" maxlength="120" required autofocus />
    <x-input name="email" label="Email address" type="email" autocomplete="username" maxlength="255" required />
    <x-input name="password" label="Password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required hint="Use at least 12 characters with uppercase, lowercase, and a number." />
    <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
    <button class="btn btn-primary btn-lg w-100 mt-2" type="submit">Create account <span aria-hidden="true">→</span></button>
</form>
<p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
