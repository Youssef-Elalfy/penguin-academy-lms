<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Dashboard') · Penguin Academy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="app-layout">
    <aside class="academy-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="academy-navigation" aria-labelledby="navigation-title">
        <div class="offcanvas-header"><h2 id="navigation-title" class="h5 mb-0">Navigation</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#academy-navigation" aria-label="Close navigation"></button></div>
        <div class="offcanvas-body">
            <a class="brand-link sidebar-brand" href="{{ route('dashboard') }}"><x-brand /></a>
            <p class="nav-section-label">{{ auth()->user()->role->label() }} workspace</p>
            <nav class="academy-nav" aria-label="Main navigation">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('*.dashboard', 'dashboard') ? 'active' : '' }}" @if(request()->routeIs('*.dashboard', 'dashboard')) aria-current="page" @endif><span aria-hidden="true">▦</span> Overview</a>
                <a href="{{ route('courses.index') }}" class="{{ request()->routeIs('courses.*') ? 'active' : '' }}" @if(request()->routeIs('courses.*')) aria-current="page" @endif><span aria-hidden="true">▤</span> {{ auth()->user()->hasRole(\App\Enums\UserRole::Student) ? 'Course catalog' : 'Courses' }}</a>
                @can('viewStudentDashboard', \App\Models\User::class)<a href="{{ route('my-courses') }}" class="{{ request()->routeIs('my-courses') ? 'active' : '' }}" @if(request()->routeIs('my-courses')) aria-current="page" @endif><span aria-hidden="true">◇</span> My courses</a>@endcan
                @can('viewAny', \App\Models\User::class)<a href="{{ route('admin.enrollments.index') }}" class="{{ request()->routeIs('admin.enrollments.*') ? 'active' : '' }}" @if(request()->routeIs('admin.enrollments.*')) aria-current="page" @endif><span aria-hidden="true">◎</span> Enrollments</a>@endcan
                @can('viewAny', \App\Models\User::class)
                <a href="{{ route('admin.teachers.index') }}" class="{{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}" @if(request()->routeIs('admin.teachers.*')) aria-current="page" @endif><span aria-hidden="true">♧</span> Teachers</a>
                @endcan
                <a href="{{ route('account.password.edit') }}" class="{{ request()->routeIs('account.password.*') ? 'active' : '' }}" @if(request()->routeIs('account.password.*')) aria-current="page" @endif><span aria-hidden="true">◇</span> Account security</a>
            </nav>
            <div class="sidebar-bottom">
                <div class="profile-chip"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div class="text-truncate"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->role->label() }}</span></div></div>
                <form method="POST" action="{{ route('logout') }}" data-submit>@csrf<button class="btn sidebar-logout w-100" type="submit">Sign out <span aria-hidden="true">↗</span></button></form>
            </div>
        </div>
    </aside>
    <div class="workspace">
        <header class="workspace-header">
            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#academy-navigation" aria-controls="academy-navigation" aria-label="Open navigation" aria-expanded="false">Menu</button>
            <span class="workspace-breadcrumb">Workspace <span aria-hidden="true">/</span> <strong>@yield('title', 'Overview')</strong></span>
            <span class="role-badge">{{ auth()->user()->role->label() }}</span>
        </header>
        <main tabindex="-1" id="main-content" class="workspace-main">
            @if(session('status'))<div class="alert alert-success mb-4" role="status">{{ session('status') }}</div>@endif
            @yield('content')
        </main>
        <footer class="workspace-footer">Penguin Academy <span>Learning, thoughtfully connected.</span></footer>
    </div>
</div>
</body>
</html>
