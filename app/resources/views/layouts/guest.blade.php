<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Welcome') · Penguin Academy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="auth-layout">
    <aside class="auth-story">
        <a class="brand-link" href="{{ route('home') }}"><x-brand /></a>
        <div class="auth-story-content">
            <p class="eyebrow text-mint">A LITTLE CURIOSITY. A BIG FUTURE.</p>
            <p class="auth-story-title">Make room for<br>what comes next.</p>
            <p>A shared space for curious minds and the people who help them grow.</p>
            <div class="story-line"></div>
            <span class="story-caption">Learn with purpose. Grow together.</span>
        </div>
        <p class="auth-footer">Penguin Academy · Your next chapter</p>
    </aside>
    <main class="auth-content" id="main-content" tabindex="-1">
        <div class="auth-form-wrap">
            @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
