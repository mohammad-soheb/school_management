{{--
    Server-driven link tabs (each tab is a normal page / route; the active one is decided by the controller).

    <x-ui.tabs :tabs="[
        ['label' => 'Overview', 'url' => route('...'), 'active' => true],
        ['label' => 'Fees',     'url' => route('...'), 'active' => false, 'badge' => 3],
        ['label' => 'Documents','url' => route('...'), 'active' => false, 'icon' => 'document-text'],
    ]" label="Student sections" />

    pill: segmented-control look instead of an underline.
--}}
@props(['tabs' => [], 'label' => 'Sections', 'pill' => false])
<nav {{ $attributes->class(['tabs', 'tabs-pill' => $pill]) }} aria-label="{{ $label }}">
    @foreach ($tabs as $tab)
        @php($active = (bool) ($tab['active'] ?? false))
        <a href="{{ $tab['url'] ?? '#' }}" @class(['tab', 'is-active' => $active]) @if ($active) aria-current="page" @endif>
            @if (! empty($tab['icon']))<x-ui.icon :name="$tab['icon']" />@endif
            {{ $tab['label'] }}
            @if (isset($tab['badge']) && $tab['badge'] !== '' && $tab['badge'] !== null)
                <span class="tab-badge">{{ $tab['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
