{{--
    <x-ui.dl>
        <x-ui.dl-item label="Admission no.">1042</x-ui.dl-item>
        <x-ui.dl-item label="Class">5-A</x-ui.dl-item>
    </x-ui.dl>
    <x-ui.dl grid> ... </x-ui.dl>     compact multi-column summary instead of a divided list
--}}
@props(['grid' => false])
<dl {{ $attributes->class(['dl', 'dl-grid' => $grid]) }}>{{ $slot }}</dl>
