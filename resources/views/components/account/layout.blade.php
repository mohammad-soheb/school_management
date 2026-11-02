{{--
    Chooses the right shell for account pages (profile, security) from who is signed in:
    Super Admin -> platform shell, parent-only login -> parent portal shell, school staff -> school shell,
    anyone without a school (between schools) -> the plain centred layout.

    <x-account.layout title="Security" heading="Sign-in & security"> ...content... </x-account.layout>
--}}
@props(['title', 'heading' => null])
@php
    $user = auth()->user();
    $membership = app(\App\Support\Tenancy\TenantContext::class)->membership();
    $isStaff = $membership?->roles->contains(fn ($role) => $role->slug !== 'parent') ?? false;

    $layout = match (true) {
        $user?->isPlatformAdmin() => 'layouts.platform',
        $membership !== null && ! $isStaff => 'layouts.portal',
        $membership !== null => 'layouts.app',
        default => 'layouts.guest',
    };
@endphp
<x-dynamic-component :component="$layout" :title="$title" :heading="$layout === 'layouts.guest' ? ($heading ?? $title) : null">
    {{ $slot }}
</x-dynamic-component>
