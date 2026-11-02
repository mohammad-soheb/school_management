{{--
    <head> contents shared by every layout.
    * The tiny inline script applies the saved/OS colour theme BEFORE first paint (no light->dark flash). It carries the CSP
      nonce (SecurityHeaders calls Vite::useCspNonce()), and is the only inline script the app uses.
    * @stack('styles') lets a page add <link>/<style> tags with @push('styles').
--}}
@props(['title', 'theme' => true])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">
@if ($theme)
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f6f7fb" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#090c14" media="(prefers-color-scheme: dark)">
@else
    <meta name="color-scheme" content="light">
@endif
<title>{{ $title }} · {{ config('app.name') }}</title>
@if ($theme)
    <script nonce="{{ Vite::cspNonce() }}">
        (function () {
            var dark = false;
            try {
                var saved = localStorage.getItem('theme');
                dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            } catch (e) {
                try { dark = window.matchMedia('(prefers-color-scheme: dark)').matches; } catch (e2) {}
            }
            if (dark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
@endif
@vite(['resources/scss/app.scss', 'resources/js/app.js'])
@stack('styles')
