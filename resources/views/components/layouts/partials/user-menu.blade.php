{{--
    Account dropdown shared by the school, platform and portal layouts.
    Every link is optional: pass null (route missing) and it is simply not shown. Logout is a POST form.
--}}
@props(['user' => null, 'profileUrl' => null, 'securityUrl' => null, 'selectUrl' => null, 'logoutUrl' => null, 'compact' => false])
@php
    $displayName = trim((string) ($user->name ?? '')) !== '' ? $user->name : 'Guest';
    $displayEmail = $user->email ?? null;
@endphp
<x-ui.dropdown align="right" width="64" label="Account menu">
    <x-slot:trigger>
        <button type="button" class="user-chip" aria-label="Account menu for {{ $displayName }}">
            <x-ui.avatar :name="$displayName" size="sm" aria-hidden="true" />
            @unless ($compact)
                <span class="hidden min-w-0 text-left md:block">
                    <span class="block max-w-[10rem] truncate text-sm font-medium leading-4 text-ink">{{ $displayName }}</span>
                </span>
                <x-ui.icon name="chevron-down" variant="m" class="hidden h-4 w-4 text-ink-faint md:block" />
            @endunless
        </button>
    </x-slot:trigger>

    <div class="px-3 pb-2 pt-2">
        <p class="truncate text-sm font-semibold text-ink">{{ $displayName }}</p>
        @if ($displayEmail)
            <p class="truncate text-xs text-ink-faint">{{ $displayEmail }}</p>
        @endif
    </div>
    <hr class="dropdown-divider">

    @if ($profileUrl)
        <x-ui.dropdown-link :href="$profileUrl" icon="user-circle">Profile</x-ui.dropdown-link>
    @endif
    @if ($securityUrl)
        <x-ui.dropdown-link :href="$securityUrl" icon="shield-check">Security</x-ui.dropdown-link>
    @endif
    @if ($selectUrl)
        <x-ui.dropdown-link :href="$selectUrl" icon="arrows-right-left">Switch school</x-ui.dropdown-link>
    @endif

    @if ($logoutUrl)
        @if ($profileUrl || $securityUrl || $selectUrl)
            <hr class="dropdown-divider">
        @endif
        <form method="POST" action="{{ $logoutUrl }}">
            @csrf
            <x-ui.dropdown-link as="button" type="submit" icon="arrow-right-start-on-rectangle" danger>Sign out</x-ui.dropdown-link>
        </form>
    @endif
</x-ui.dropdown>
