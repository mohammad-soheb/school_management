{{--
    <x-ui.avatar name="Aarav Sharma" size="md" />                       initials on a tinted colour picked from the name
    <x-ui.avatar name="Aarav Sharma" src="{{ $photoUrl }}" size="lg" />
    size: xs | sm | md | lg | xl
    The avatar is announced as the person's name; pass aria-hidden="true" when the name is printed right next to it.
--}}
@props(['name' => '', 'src' => null, 'size' => 'md'])
@php
    $sizes = ['xs' => 'avatar-xs', 'sm' => 'avatar-sm', 'md' => 'avatar-md', 'lg' => 'avatar-lg', 'xl' => 'avatar-xl'];
    $tones = ['avatar-tone-0', 'avatar-tone-1', 'avatar-tone-2', 'avatar-tone-3', 'avatar-tone-4', 'avatar-tone-5', 'avatar-tone-6', 'avatar-tone-7'];

    $clean = trim((string) $name);
    $words = $clean === '' ? [] : array_values(array_filter(preg_split('/[\s._-]+/u', $clean) ?: []));
    $initials = '';
    if (count($words) > 0) {
        $initials = mb_strtoupper(mb_substr($words[0], 0, 1));
        if (count($words) > 1) {
            $initials .= mb_strtoupper(mb_substr($words[count($words) - 1], 0, 1));
        }
    }
    $tone = $tones[crc32(mb_strtolower($clean)) % count($tones)];
@endphp
<span {{ $attributes->class(['avatar', $sizes[$size] ?? $sizes['md'], $tone => ! $src]) }} @if ($clean !== '' && ! $attributes->has('aria-hidden')) role="img" aria-label="{{ $clean }}" @endif>
    @if ($src)
        <img src="{{ $src }}" alt="" loading="lazy" decoding="async">
    @else
        {{ $initials !== '' ? $initials : '?' }}
    @endif
</span>
