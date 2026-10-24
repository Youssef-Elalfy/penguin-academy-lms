@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<p class="eyebrow">GOOD TO SEE YOU AGAIN</p>
<h1 class="auth-heading">Welcome back.</h1>
<p class="text-secondary mb-4">Sign in to your Penguin Academy workspace.</p>
<x-errors />
<form method="POST" action="{{ route('login.store') }}" data-submit>
    @csrf
    <x-input name="email" label="Email address" type="email" autocomplete="username" maxlength="255" required autofocus />
    <x-input name="password" label="Password" type="password" autocomplete="current-password" required />
    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" value="1" id="remember"><label class="form-check-label" for="remember">Keep me signed in on this device</label></div>
    <button class="btn btn-primary btn-lg w-100" type="submit">Sign in <span aria-hidden="true">→</span></button>
</form>
<p class="auth-switch">New to the academy? <a href="{{ route('register') }}">Create a student account</a></p>
@endsection
