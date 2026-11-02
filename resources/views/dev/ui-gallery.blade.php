{{--
    UI gallery: every component, every variant, plus realistic sample pages. Local only (routes/dev.php -> /_ui).
    Uses <x-layouts.app> with a demo sidebar and a demo user so it renders for a guest with no school and no routes.
--}}
@php
    $menu = [
        ['label' => 'Overview', 'items' => [
            ['label' => 'Dashboard', 'url' => '#', 'icon' => 'home', 'active' => false, 'badge' => null],
        ]],
        ['label' => 'People', 'items' => [
            ['label' => 'Students', 'url' => '#students-page', 'icon' => 'academic-cap', 'active' => true, 'badge' => null],
            ['label' => 'Guardians', 'url' => '#', 'icon' => 'user-group', 'active' => false, 'badge' => null],
            ['label' => 'Staff', 'url' => '#', 'icon' => 'identification', 'active' => false, 'badge' => null],
        ]],
        ['label' => 'Academics', 'items' => [
            ['label' => 'Attendance', 'url' => '#', 'icon' => 'clipboard-document-check', 'active' => false, 'badge' => null],
            ['label' => 'Exams & marks', 'url' => '#', 'icon' => 'document-text', 'active' => false, 'badge' => null],
            ['label' => 'Timetable', 'url' => '#', 'icon' => 'calendar-days', 'active' => false, 'badge' => null],
        ]],
        ['label' => 'Finance', 'items' => [
            ['label' => 'Fee collection', 'url' => '#', 'icon' => 'banknotes', 'active' => false, 'badge' => '3'],
            ['label' => 'Daily close', 'url' => '#', 'icon' => 'lock-closed', 'active' => false, 'badge' => null],
            ['label' => 'Reports', 'url' => '#', 'icon' => 'chart-bar', 'active' => false, 'badge' => null],
        ]],
        ['label' => 'Settings', 'items' => [
            ['label' => 'School setup', 'url' => '#', 'icon' => 'building-library', 'active' => false, 'badge' => null],
            ['label' => 'Users & roles', 'url' => '#', 'icon' => 'shield-check', 'active' => false, 'badge' => null],
        ]],
    ];

    $demoUser = auth()->user() ?? new \App\Models\User(['name' => 'Priya Nair', 'email' => 'priya.nair@sunrise.example']);

    $sections = [
        'foundations' => 'Foundations',
        'buttons' => 'Buttons',
        'feedback' => 'Badges & alerts',
        'stats' => 'Stats & cards',
        'forms' => 'Forms',
        'tables' => 'Tables',
        'navigation' => 'Navigation',
        'overlays' => 'Overlays',
        'dataviz' => 'Charts & progress',
        'patterns' => 'Misc',
        'students-page' => 'Sample: students',
    ];
@endphp
<x-layouts.app title="UI gallery" :menu="$menu" :user="$demoUser">
    <span id="top"></span>

    <x-ui.page-header title="UI gallery" description="Every component in every variant. Toggle the moon icon in the top bar to check dark mode; resize the window to check phones."
                      :breadcrumbs="[['label' => 'Developer', 'url' => url('/_ui')], ['label' => 'UI gallery']]">
        <x-slot:actions>
            <x-ui.dropdown align="right" width="56">
                <x-slot:trigger>
                    <x-ui.button variant="secondary" icon="eye" icon-right="chevron-down">Other previews</x-ui.button>
                </x-slot:trigger>
                <x-ui.dropdown-link href="{{ url('/_ui/portal') }}" icon="device-phone-mobile">Parent portal layout</x-ui.dropdown-link>
                <x-ui.dropdown-link href="{{ url('/_ui/print') }}" icon="printer">Print layout (receipt)</x-ui.dropdown-link>
                <x-ui.dropdown-link href="{{ url('/_ui/auth/login') }}" icon="lock-closed">Sign-in page</x-ui.dropdown-link>
                <x-ui.dropdown-link href="{{ url('/_ui/errors/404') }}" icon="exclamation-triangle">Error page (404)</x-ui.dropdown-link>
                <x-ui.dropdown-link href="{{ url('/_ui?invalid=1') }}" icon="x-circle">Forms with errors</x-ui.dropdown-link>
            </x-ui.dropdown>
        </x-slot:actions>
    </x-ui.page-header>

    <nav class="mb-10 flex flex-wrap gap-2" aria-label="Gallery sections">
        @foreach ($sections as $anchor => $label)
            <a href="#{{ $anchor }}" class="filter-chip transition-colors duration-150 hover:border-brand-500 hover:text-brand-ink">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="space-y-16">
        @include('dev.gallery.foundations')
        @include('dev.gallery.buttons')
        @include('dev.gallery.feedback')
        @include('dev.gallery.stats-cards')
        @include('dev.gallery.forms')
        @include('dev.gallery.tables')
        @include('dev.gallery.navigation')
        @include('dev.gallery.overlays')
        @include('dev.gallery.dataviz')
        @include('dev.gallery.patterns')
        @include('dev.gallery.students-page')
    </div>
</x-layouts.app>
