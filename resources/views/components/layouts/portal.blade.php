{{--
    Parent portal layout: phone first. Compact top bar (child switcher), bottom tab bar on phones/tablets, top navigation on desktop.
    Data comes from App\View\Components\Layouts\Portal.

    <x-layouts.portal :title="'Attendance'" :children="[
        ['id' => 11, 'name' => 'Aarav Sharma', 'class' => '5-A', 'active' => true],
        ['id' => 12, 'name' => 'Diya Sharma',  'class' => '2-B', 'active' => false],
    ]"> ... </x-layouts.portal>

    Switching a child POSTs student_id to route('parent.children.switch') when that route exists.
--}}
@php
    $canSwitchChild = $switchUrl && count($childList) > 1;
    $childName = $activeChild['name'] ?? null;
    $childMeta = $activeChild['class'] ?? null;
@endphp
<x-layouts.partials.document :title="$title" body-class="portal-body">
    <div class="portal-shell" x-data="{ more: false }" @keydown.escape.window="more = false">
        <a href="#main" class="skip-link">Skip to main content</a>

        <header class="portal-topbar">
            <div class="portal-topbar-inner">
                <div class="portal-brand hidden sm:flex">
                    <span class="brand-tile">
                        @if ($brand['logo'])<img src="{{ $brand['logo'] }}" alt="" loading="lazy">@else{{ mb_strtoupper(mb_substr(trim($brand['name']) ?: 'S', 0, 1)) }}@endif
                    </span>
                    <span class="min-w-0">
                        <span class="brand-name">{{ $brand['name'] }}</span>
                        <span class="brand-sub">Parent portal</span>
                    </span>
                </div>

                <nav class="portal-nav" aria-label="Main">
                    @foreach ($tabs as $tab)
                        <a href="{{ $tab['url'] }}" @class(['portal-nav-link', 'is-active' => $tab['active'] ?? false]) @if ($tab['active'] ?? false) aria-current="page" @endif>
                            <x-ui.icon :name="$tab['icon'] ?? 'squares-2x2'" />{{ $tab['label'] }}
                        </a>
                    @endforeach
                    @foreach ($overflow as $tab)
                        <a href="{{ $tab['url'] }}" @class(['portal-nav-link', 'is-active' => $tab['active'] ?? false]) @if ($tab['active'] ?? false) aria-current="page" @endif>
                            <x-ui.icon :name="$tab['icon'] ?? 'squares-2x2'" />{{ $tab['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="portal-actions min-w-0 flex-1 justify-end">
                    @if ($activeChild)
                        @if ($canSwitchChild)
                            <x-ui.dropdown align="right" width="72" label="Switch child" class="min-w-0 max-w-full">
                                <x-slot:trigger>
                                    <button type="button" class="child-switch max-w-full">
                                        <x-ui.avatar :name="$childName" size="md" aria-hidden="true" />
                                        <span class="min-w-0 flex-1">
                                            <span class="child-switch-name">{{ $childName }}</span>
                                            @if ($childMeta)<span class="child-switch-meta">Class {{ $childMeta }}</span>@endif
                                        </span>
                                        <x-ui.icon name="chevron-up-down" variant="m" class="h-4 w-4 shrink-0 text-ink-faint" />
                                    </button>
                                </x-slot:trigger>

                                <p class="dropdown-label">Your children</p>
                                @foreach ($childList as $child)
                                    <form method="POST" action="{{ $switchUrl }}">
                                        @csrf
                                        <input type="hidden" name="student_id" value="{{ $child['id'] }}">
                                        <button type="submit" role="menuitem" class="dropdown-item !min-h-touch" @if ($child['active']) aria-current="true" @endif>
                                            <x-ui.avatar :name="$child['name']" size="sm" aria-hidden="true" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate">{{ $child['name'] }}</span>
                                                @if ($child['class'])<span class="block truncate text-xs font-normal text-ink-faint">Class {{ $child['class'] }}</span>@endif
                                            </span>
                                            @if ($child['active'])<x-ui.icon name="check" variant="m" class="!text-brand-ink" />@endif
                                        </button>
                                    </form>
                                @endforeach
                            </x-ui.dropdown>
                        @else
                            <div class="child-switch max-w-full cursor-default">
                                <x-ui.avatar :name="$childName" size="md" aria-hidden="true" />
                                <span class="min-w-0 flex-1">
                                    <span class="child-switch-name">{{ $childName }}</span>
                                    @if ($childMeta)<span class="child-switch-meta">Class {{ $childMeta }}</span>@endif
                                </span>
                            </div>
                        @endif
                    @endif

                    <x-layouts.partials.theme-toggle />
                    <x-layouts.partials.user-menu :user="$user" :profile-url="$profileUrl" :security-url="$securityUrl" :logout-url="$logoutUrl" compact />
                </div>
            </div>
        </header>

        <main id="main" tabindex="-1" class="portal-content animate-fade-up">
            {{ $slot }}
        </main>

        @if (count($tabs) > 0)
            <nav class="portal-tabbar" aria-label="Main">
                <div class="portal-tabbar-inner">
                    @foreach ($tabs as $tab)
                        <a href="{{ $tab['url'] }}" @class(['portal-tab', 'is-active' => $tab['active'] ?? false]) @if ($tab['active'] ?? false) aria-current="page" @endif>
                            <x-ui.icon :name="$tab['icon'] ?? 'squares-2x2'" />
                            <span>{{ $tab['label'] }}</span>
                        </a>
                    @endforeach

                    @if (count($overflow) > 0)
                        <div class="relative flex min-w-0 flex-1" @click.outside="more = false">
                            <button type="button" @class(['portal-tab', 'w-full', 'is-active' => $overflowActive]) @click="more = !more" :aria-expanded="more ? 'true' : 'false'" aria-haspopup="true">
                                <x-ui.icon name="ellipsis-horizontal" />
                                <span>More</span>
                            </button>
                            <div class="portal-more" x-show="more" x-cloak x-transition.opacity.duration.150ms>
                                @foreach ($overflow as $tab)
                                    <a href="{{ $tab['url'] }}" @class(['dropdown-item', '!min-h-touch', 'is-active' => $tab['active'] ?? false]) @if ($tab['active'] ?? false) aria-current="page" @endif>
                                        <x-ui.icon :name="$tab['icon'] ?? 'squares-2x2'" />{{ $tab['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </nav>
        @endif
    </div>
</x-layouts.partials.document>
