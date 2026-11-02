{{--
    Password input with a show/hide button (extra to the core contract; used by the auth screens).

    <x-form.password name="password" label="Password" autocomplete="current-password" required>
        <x-slot:aside><a href="{{ route('password.request') }}" class="auth-link text-sm">Forgot password?</a></x-slot:aside>
    </x-form.password>

    The value is never re-filled. `aside` is shown at the right end of the label row.
--}}
@props(['name' => 'password', 'label' => 'Password', 'hint' => null, 'required' => false])
@php
    $fieldId = \App\View\Components\Support\Field::id($name, $attributes->get('id'));
    $error = \App\View\Components\Support\Field::error($errors ?? null, $name);
    $describedBy = trim(($hint && ! $error ? $fieldId.'-hint ' : '').($error ? $fieldId.'-error' : ''));

    $controlAttributes = $attributes->except(['class', 'id'])
        ->merge(array_filter([
            'name' => $name,
            'id' => $fieldId,
            'required' => $required ? true : null,
            'aria-required' => $required ? 'true' : null,
            'aria-invalid' => $error ? 'true' : null,
            'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        ], fn ($v) => $v !== null))
        ->class(['input']);
@endphp
<div {{ $attributes->only('class')->class(['field']) }} x-data="{ visible: false }">
    <div class="mb-1.5 flex items-center justify-between gap-3">
        <label for="{{ $fieldId }}" @class(['label', 'label-required' => $required, '!mb-0'])>{{ $label }}</label>
        @isset($aside){{ $aside }}@endisset
    </div>

    <div @class(['input-group', 'is-invalid' => $error])>
        <input :type="visible ? 'text' : 'password'" type="password" autocapitalize="off" spellcheck="false" {{ $controlAttributes }}>
        <button type="button" class="input-affix cursor-pointer !px-3.5 text-ink-faint transition-colors duration-150 hover:text-ink focus-visible:text-ink"
                @click="visible = !visible" :aria-pressed="visible ? 'true' : 'false'" aria-label="Show password" title="Show password">
            <x-ui.icon name="eye" x-show="!visible" class="h-5 w-5" />
            <x-ui.icon name="eye-slash" x-show="visible" x-cloak class="h-5 w-5" />
        </button>
    </div>

    @if ($hint && ! $error)
        <p class="hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif
    <x-form.error :name="$name" :id="$fieldId" />
</div>
