{{--
    Sortable table header cell. Reads ?sort= and ?dir= from the request, keeps every other query parameter
    (filters, search) and resets ?page.

    <x-ui.th column="name" label="Student" />          sortable
    <x-ui.th column="admission_no">Adm. no.</x-ui.th>   label from the slot
    <x-ui.th>Class</x-ui.th>                            plain header cell
    <x-ui.th column="amount" class="num" />             right aligned numeric column

    The controller must whitelist the sortable columns itself (never order by raw request input).
--}}
@props(['column' => null, 'label' => null])
@php
    $sortable = filled($column);
    $labelHtml = $label !== null
        ? e($label)
        : ($slot->isNotEmpty() ? (string) $slot : e($column ? \Illuminate\Support\Str::headline($column) : ''));
    $plainLabel = trim(html_entity_decode(strip_tags($labelHtml)));

    $activeColumn = request()->query('sort');
    $direction = strtolower((string) request()->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
    $isSorted = $sortable && $activeColumn === $column;
    $nextDirection = $isSorted && $direction === 'asc' ? 'desc' : 'asc';
    $url = $sortable ? request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDirection, 'page' => null]) : null;
    $ariaSort = $sortable ? ($isSorted ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none') : null;
    $icon = $isSorted ? ($direction === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevron-up-down';
@endphp
<th scope="col" {{ $attributes }} @if ($ariaSort) aria-sort="{{ $ariaSort }}" @endif>
    @if ($sortable)
        <a href="{{ $url }}" @class(['th-sort', 'is-sorted' => $isSorted]) title="Sort by {{ $plainLabel }}">{!! $labelHtml !!}<x-ui.icon :name="$icon" variant="m" /></a>
    @else
        {!! $labelHtml !!}
    @endif
</th>
