@extends('layouts.guest')
@section('title', 'Welcome')
@section('content')
<p class="eyebrow">WELCOME TO PENGUIN ACADEMY</p>
<h2 class="auth-heading">Your next chapter<br>starts here.</h2>
<p class="text-secondary mb-4">A place to learn, share your knowledge, and stay connected to your academy.</p>
@auth
<a class="btn btn-primary btn-lg w-100" href="{{ route('dashboard') }}">Open your workspace <span aria-hidden="true">→</span></a>
@else
<a class="btn btn-primary btn-lg w-100 mb-3" href="{{ route('register') }}">Create a student account <span aria-hidden="true">→</span></a>
<a class="btn btn-outline-secondary btn-lg w-100" href="{{ route('login') }}">Sign in to your workspace</a>
@endauth
<p class="small text-secondary mt-4">Teaching at Penguin Academy? Your administrator will provide your account.</p>
@endsection
