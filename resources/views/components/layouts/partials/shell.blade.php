{{--
    The application chrome for the school area and the platform backoffice: sidebar + sticky topbar + content.
    Not used directly by pages; <x-layouts.app> and <x-layouts.platform> feed it.

    menu         Menu::build() result: [['label' => 'Students', 'items' => [['label','url','icon','active','badge'], ...]], ...]
    brand        ['name' => ..., 'sub' => ?string, 'logo' => ?string(url), 'badge' => ?string]
    memberships  collection/array of ['id' => school id, 'name' => ..., 'sub' => ..., 'current' => bool] (switcher shows when > 1)
--}}
@props([
    'menu' => [],
    'user' => null,
    'brand' => [],
    'memberships' => [],
    'switchUrl' => null,
    'selectUrl' => null,
    'academicYear' => null,
    'searchUrl' => null,
    'profileUrl' => null,
    'securityUrl' => null,
    'logoutUrl' => null,
])
@php
    $brandName = $brand['name'] ?? config('app.name');
    $brandSub = $brand['sub'] ?? null;
    $brandLogo = $brand['logo'] ?? null;
    $brandBadge = $brand['badge'] ?? null;
    $brandInitial = mb_strtoupper(mb_substr(trim((string) $brandName) ?: 'S', 0, 1));
    $canSwitch = $switchUrl && count($memberships) > 1;
@endphp
<div class="app-shell" x-data="{ drawer: false }" @keydown.escape.window="drawer = false" @resize.window.debounce.150ms="if (window.innerWidth >= 1024) drawer = false">
    <a href="#main" class="skip-link">Skip to main content</a>

    <div class="app-backdrop" x-show="drawer" x-cloak x-transition.opacity.duration.200ms @click="drawer = false" aria-hidden="true"></div>

    <aside id="app-sidebar" class="app-sidebar" :class="{ 'is-open': drawer }" aria-label="Sidebar" x-trap.noscroll="drawer">
        <div class="app-sidebar-head">
            @if ($canSwitch)
                <x-ui.dropdown align="left" width="72" label="Switch school" class="block min-w-0 flex-1">
                    <x-slot:trigger>
                        <button type="button" class="app-brand">
                            <span class="brand-tile">
                                @if ($brandLogo)<img src="{{ $brandLogo }}" alt="" loading="lazy">@else{{ $brandInitial }}@endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="brand-name">{{ $brandName }}</span>
                                @if ($brandSub)<span class="brand-sub">{{ $brandSub }}</span>@endif
                            </span>
                            <x-ui.icon name="chevron-up-down" variant="m" class="h-4 w-4 shrink-0 text-ink-faint" />
                        </button>
                    </x-slot:trigger>

                    <p class="dropdown-label">Your schools</p>
                    @foreach ($memberships as $membership)
                        <form method="POST" action="{{ $switchUrl }}">
                            @csrf
                            <input type="hidden" name="school_id" value="{{ $membership['id'] }}">
                            <button type="submit" role="menuitem" class="dropdown-item" @if ($membership['current']) aria-current="true" @endif>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate">{{ $membership['name'] }}</span>
                                    @if (! empty($membership['sub']))<span class="block truncate text-xs font-normal text-ink-faint">{{ $membership['sub'] }}</span>@endif
                                </span>
                                @if ($membership['current'])<x-ui.icon name="check" variant="m" class="!text-brand-ink" />@endif
                            </button>
                        </form>
                    @endforeach
                    @if ($selectUrl)
                        <hr class="dropdown-divider">
                        <x-ui.dropdown-link :href="$selectUrl" icon="squares-2x2">All schools</x-ui.dropdown-link>
                    @endif
                </x-ui.dropdown>
            @else
                <div class="app-brand">
                    <span class="brand-tile">
                        @if ($brandLogo)<img src="{{ $brandLogo }}" alt="" loading="lazy">@else{{ $brandInitial }}@endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="brand-name">{{ $brandName }}</span>
                        @if ($brandBadge)
                            <span class="mt-0.5 block"><x-ui.badge tone="brand">{{ $brandBadge }}</x-ui.badge></span>
                        @elseif ($brandSub)
                            <span class="brand-sub">{{ $brandSub }}</span>
                        @endif
                    </span>
                </div>
            @endif
        </div>

        <nav class="app-nav" aria-label="Main">
            @forelse ($menu as $group)
                <div class="nav-group">
                    <p class="nav-group-label" id="nav-group-{{ \Illuminate\Support\Str::slug($group['label']) }}">{{ $group['label'] }}</p>
                    <ul class="space-y-0.5" aria-labelledby="nav-group-{{ \Illuminate\Support\Str::slug($group['label']) }}">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a href="{{ $item['url'] }}" @class(['nav-link', 'is-active' => $item['active'] ?? false]) @if ($item['active'] ?? false) aria-current="page" @endif>
                                    <x-ui.icon :name="$item['icon'] ?? 'squares-2x2'" />
                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                    @if (! empty($item['badge']))<span class="nav-badge">{{ $item['badge'] }}</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="px-3 text-sm text-ink-faint">No menu items yet.</p>
            @endforelse
        </nav>

        @if ($academicYear)
            {{-- The topbar badge is hidden on phones; the drawer footer keeps the year visible there. --}}
            <div class="app-sidebar-foot sm:hidden">
                <x-ui.icon name="calendar-days" />
                <span>Year <strong>{{ $academicYear }}</strong></span>
            </div>
        @endif
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="icon-btn lg:hidden" @click="drawer = true" aria-controls="app-sidebar" :aria-expanded="drawer ? 'true' : 'false'" aria-label="Open navigation">
                <x-ui.icon name="bars-3" />
            </button>

            @if ($searchUrl)
                <form method="GET" action="{{ $searchUrl }}" role="search" class="app-search" data-lock="false"
                      @keydown.window="if ($event.key === '/' && !$event.target.closest('input, textarea, select, [contenteditable]')) { $event.preventDefault(); $refs.q.focus() }">
                    <label for="app-search-q" class="sr-only">Search students</label>
                    <x-ui.icon name="magnifying-glass" />
                    <input id="app-search-q" x-ref="q" type="search" name="q" class="input" placeholder="Search students…" autocomplete="off" enterkeyhint="search">
                    <kbd aria-hidden="true">/</kbd>
                </form>
            @endif

            <div class="app-topbar-actions">
                @if ($academicYear)
                    <x-ui.badge tone="brand" class="hidden sm:inline-flex" title="Current academic year">
                        <x-ui.icon name="calendar-days" variant="m" />{{ $academicYear }}
                    </x-ui.badge>
                @endif
                <x-layouts.partials.theme-toggle />
                <x-layouts.partials.user-menu :user="$user" :profile-url="$profileUrl" :security-url="$securityUrl" :select-url="$selectUrl" :logout-url="$logoutUrl" />
            </div>
        </header>

        <main id="main" tabindex="-1" class="app-content">
            <div class="app-content-inner animate-fade-up">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>
