{{--
    Shared shell for the HTTP error pages. STANDALONE on purpose: error pages render when things are broken, so this
    file touches no session, auth, database, tenant context or menu, and keeps working when the Vite build is missing
    (it falls back to a few inline styles). Only config('app.name') and url('/') are used.

    Sections: code, title, heading, message, icon (SVG <path> markup), actions (extra buttons, optional)
--}}
@php
    $appName = config('app.name', 'School ERP');
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $hasAssets = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title') · {{ $appName }}</title>
    <script nonce="{{ $nonce }}">
        (function () {
            var dark = false;
            try {
                var saved = localStorage.getItem('theme');
                dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            } catch (e) {}
            if (dark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    @if ($hasAssets)
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @else
        <style nonce="{{ $nonce }}">
            body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f6f7fb;color:#111827}
            .error-shell{max-width:32rem;margin:0 auto;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3rem 1.25rem;text-align:center;box-sizing:border-box}
            .error-card{width:100%;box-sizing:border-box;background:#fff;border:1px solid #e4e8f0;border-radius:1.5rem;padding:2.5rem 2rem}
            .error-icon{display:none}
            .error-code{font-size:4.5rem;font-weight:600;color:#4f46e5;line-height:1;margin:0}
            .error-title{font-size:1.5rem;margin:1rem 0 .5rem}
            .error-text{color:#475061;margin:0 auto;max-width:24rem;line-height:1.6}
            .error-actions{margin-top:2rem}
            .btn{display:inline-block;padding:.75rem 1.5rem;border-radius:.75rem;background:#4f46e5;color:#fff;text-decoration:none;font-weight:500}
            .btn-secondary{background:#fff;color:#111827;border:1px solid #cbd2e0}
            .js-only{display:none}
            .error-foot{font-size:.75rem;color:#626b7c}
        </style>
    @endif
</head>
<body class="error-body">
    <main class="error-shell">
        <div class="error-card">
            <div class="error-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" focusable="false">
                    @yield('icon')
                </svg>
            </div>
            <p class="error-code" aria-hidden="true">@yield('code')</p>
            <h1 class="error-title">@yield('heading')</h1>
            <p class="error-text">@yield('message')</p>

            <div class="error-actions">
                <a href="{{ url('/') }}" class="btn btn-primary btn-lg">Go home</a>
                @yield('actions')
            </div>
        </div>
        <p class="error-foot">{{ $appName }} &middot; Error @yield('code')</p>
    </main>
</body>
</html>
