{{--
    Authentication layout: brand panel + form card.

    <x-layouts.guest :title="'Sign in'" heading="Welcome back" subheading="Sign in to your school account">
        ...form...
    </x-layouts.guest>

    inline-status: set it on pages that print session('status') themselves (so it is not toasted twice).
--}}
@props(['title', 'heading' => null, 'subheading' => null, 'inlineStatus' => false])
@php
    $appName = config('app.name');
@endphp
<x-layouts.partials.document :title="$title" body-class="auth-body" :inline-status="$inlineStatus">
    <div class="auth-shell">
        <aside class="auth-panel" aria-label="About {{ $appName }}">
            <div class="auth-panel-brand">
                <span class="brand-tile"><x-ui.icon name="academic-cap" variant="s" class="h-5 w-5" /></span>
                {{ $appName }}
            </div>

            <div>
                <h2 class="auth-panel-title">One calm place to run your whole school.</h2>
                <p class="auth-panel-text">Attendance, fees, exams and parent communication, built for the way Indian schools actually work.</p>

                <ul class="auth-feature-list">
                    <li class="auth-feature">
                        <span class="auth-feature-icon"><x-ui.icon name="clipboard-document-check" /></span>
                        <span><strong>Attendance in a few taps</strong>Open the class, mark exceptions, save.</span>
                    </li>
                    <li class="auth-feature">
                        <span class="auth-feature-icon"><x-ui.icon name="banknotes" /></span>
                        <span><strong>Fee collection you can trust</strong>Look up a student, take payment, print the receipt.</span>
                    </li>
                    <li class="auth-feature">
                        <span class="auth-feature-icon"><x-ui.icon name="device-phone-mobile" /></span>
                        <span><strong>A parent portal for any phone</strong>Light on data, clear on what changed.</span>
                    </li>
                </ul>
            </div>

            <p class="auth-panel-foot">&copy; {{ date('Y') }} {{ $appName }}</p>
        </aside>

        <main id="main" class="auth-main">
            <div class="auth-toolbar">
                <x-layouts.partials.theme-toggle />
            </div>

            <div class="w-full max-w-md animate-fade-up">
                <div class="auth-mobile-brand">
                    <span class="brand-tile"><x-ui.icon name="academic-cap" variant="s" class="h-5 w-5" /></span>
                    {{ $appName }}
                </div>

                <div class="auth-card">
                    @if ($heading)
                        <div class="mb-6">
                            <h1 class="auth-heading">{{ $heading }}</h1>
                            @if ($subheading)
                                <p class="auth-subheading">{{ $subheading }}</p>
                            @endif
                        </div>
                    @endif

                    {{ $slot }}
                </div>

                <p class="auth-footer">Protected sign-in &middot; Your data stays inside your school.</p>
            </div>
        </main>
    </div>
</x-layouts.partials.document>
