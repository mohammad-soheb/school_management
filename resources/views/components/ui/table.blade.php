{{--
    <x-ui.table caption="Students" :dense="false">
        <x-slot:head>
            <x-ui.th column="name" label="Student" />
            <x-ui.th column="class">Class</x-ui.th>
            <x-ui.th class="num">Balance</x-ui.th>
            <th class="col-actions"><span class="sr-only">Actions</span></th>
        </x-slot:head>

        @foreach ($students as $student)
            <tr>...</tr>
        @endforeach
    </x-ui.table>

    * The `head` slot holds the <th> cells only; the component wraps them in <thead><tr>.
    * The default slot holds the <tr> rows of <tbody>. Optional `foot` slot holds <tr> rows of <tfoot>.
    * The wrapper scrolls horizontally on its own; the page never does. `tall` adds a max height with a sticky header.
    * `caption` becomes a visually hidden <caption> and the accessible name of the scroll region.
--}}
@props(['dense' => false, 'tall' => false, 'caption' => null])
<div {{ $attributes->class(['table-wrap', 'table-wrap-tall' => $tall]) }} tabindex="0" @if ($caption) role="region" aria-label="{{ $caption }}" @endif>
    <table @class(['data-table', 'data-table-dense' => $dense])>
        @if ($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endif
        @if (isset($head) && $head->isNotEmpty())
            <thead>
                <tr>{{ $head }}</tr>
            </thead>
        @endif
        <tbody>{{ $slot }}</tbody>
        @if (isset($foot) && $foot->isNotEmpty())
            <tfoot>{{ $foot }}</tfoot>
        @endif
    </table>
</div>
