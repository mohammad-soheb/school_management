{{--
    Button row at the end of a form: stacked full-width (primary first) on phones, right-aligned from `sm`.
    <x-form.actions sticky>
        <x-ui.button href="{{ route('...') }}" variant="ghost">Cancel</x-ui.button>
        <x-ui.button type="submit">Save changes</x-ui.button>
    </x-form.actions>
    sticky: pins the row to the bottom of the screen on phones (long forms), static from `sm`.
    Put the primary action LAST in the markup: it ends up on the right on desktop and on top on phones.
--}}
@props(['sticky' => false])
<div {{ $attributes->class(['form-actions', 'is-sticky' => $sticky]) }}>{{ $slot }}</div>
