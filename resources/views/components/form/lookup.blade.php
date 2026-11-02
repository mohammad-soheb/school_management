{{--
    Async search-select (combobox). Used for "find a student" in fee entry.

    <x-form.lookup name="student_id" label="Student" :url="route('school.students.lookup')"
                   placeholder="Admission no. or name" :value="$student?->id" :display="$student?->name" required />

    The endpoint receives GET {url}?q=term and returns JSON:
        [{"id": 1, "label": "Aarav Sharma", "sublabel": "Adm. 1042 · Class 5-A"}]
    The chosen id is posted in a hidden input called `name`. After a failed validation, pass `display` (and `sublabel`) for
    the old id so the box can show who was picked: the old id itself is restored automatically.
    Events bubbling from the wrapper:  lookup-selected  (detail: name, id, label, sublabel, item)  /  lookup-cleared
    e.g.  <div @lookup-selected="loadCharges($event.detail.id)"> ... </div>
    Keyboard: type, Up/Down, Enter to choose, Esc to close.
--}}
@props(['name', 'url', 'label' => null, 'placeholder' => 'Search…', 'value' => null, 'display' => null, 'sublabel' => null, 'hint' => null, 'required' => false, 'minChars' => 2])
@php
    $fieldKey = \App\View\Components\Support\Field::key($name);
    $uid = \App\View\Components\Support\Field::id($name, $attributes->get('id'));
    $error = \App\View\Components\Support\Field::error($errors ?? null, $name);
    $currentId = old($fieldKey, $value);
    $config = [
        'name' => $name,
        'url' => $url,
        'uid' => $uid,
        'value' => $currentId === null ? '' : (string) $currentId,
        'display' => (string) ($display ?? ''),
        'sublabel' => (string) ($sublabel ?? ''),
        'minChars' => (int) $minChars,
    ];
    $describedBy = trim(($hint && ! $error ? $uid.'-hint ' : '').($error ? $uid.'-error' : ''));
@endphp
<div {{ $attributes->only('class')->class(['field', 'lookup']) }} x-data="lookup({{ \Illuminate\Support\Js::from($config) }})" @click.outside="close()" @keydown.escape="if (open) { $event.stopPropagation(); close() }">
    @if ($label)
        <label for="{{ $uid }}-input" @class(['label', 'label-required' => $required])>{{ $label }}</label>
    @endif

    <input type="hidden" name="{{ $name }}" x-ref="hidden" :value="id" value="{{ $config['value'] }}">

    {{-- A record is chosen --}}
    <div x-show="hasValue" @if ($config['value'] === '') x-cloak @endif @class(['lookup-selected', 'is-invalid' => $error])>
        <span class="min-w-0">
            <span class="block truncate text-sm font-medium text-ink" x-text="shownLabel">{{ $config['display'] !== '' ? $config['display'] : '#'.$config['value'] }}</span>
            <span class="block truncate text-xs text-ink-faint" x-show="sublabel" x-text="sublabel">{{ $config['sublabel'] }}</span>
        </span>
        <button type="button" class="btn btn-ghost btn-sm" @click="clear()">Change</button>
    </div>

    {{-- Search box --}}
    <div x-show="!hasValue" @if ($config['value'] !== '') x-cloak @endif>
        <div class="lookup-control">
            <x-ui.icon name="magnifying-glass" />
            <input id="{{ $uid }}-input" type="text" x-ref="input" x-model="query" class="input"
                   role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-controls="{{ $uid }}-list"
                   :aria-expanded="open ? 'true' : 'false'" :aria-activedescendant="activeId()"
                   @if ($required) aria-required="true" @endif @if ($error) aria-invalid="true" @endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                   placeholder="{{ $placeholder }}" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search"
                   @input="onInput()" @focus="if (results.length) open = true"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                   @keydown.home="edge(false)" @keydown.end="edge(true)" @keydown.enter="enter($event)">
            <span class="lookup-spinner" x-show="loading" x-cloak aria-hidden="true"></span>
        </div>

        <ul id="{{ $uid }}-list" role="listbox" class="lookup-list" x-show="open" x-cloak aria-label="{{ $label ?? 'Search results' }}">
            <template x-for="(item, index) in results" :key="item.id">
                <li role="option" class="lookup-option" :id="optionId(index)" :aria-selected="index === active ? 'true' : 'false'"
                    :class="{ 'is-active': index === active }" @click="choose(item)" @mousemove="active = index">
                    <span class="lookup-option-label" x-text="item.label"></span>
                    <span class="lookup-option-sub" x-show="item.sublabel" x-text="item.sublabel"></span>
                </li>
            </template>
            <li class="lookup-empty" x-show="!loading && searched && !failed && results.length === 0">No matches. Check the spelling or try the admission number.</li>
            <li class="lookup-empty" x-show="failed" role="alert">Search failed. Check your connection and try again.</li>
        </ul>
    </div>

    <span class="sr-only" role="status" aria-live="polite" x-text="status"></span>

    @if ($hint && ! $error)
        <p class="hint" id="{{ $uid }}-hint">{{ $hint }}</p>
    @endif
    <x-form.error :name="$name" :id="$uid" />
</div>
