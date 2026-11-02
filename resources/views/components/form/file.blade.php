{{--
    <x-form.file name="photo" label="Student photo" accept="image/jpeg,image/png" hint="JPG or PNG, up to 2 MB" />
    <x-form.file name="documents[]" label="Documents" multiple />      remember :files="true" on <x-form.form>

    Click or drag & drop; the chosen file names and sizes are listed. The real <input type="file"> stays focusable
    (keyboard users tab to it), so the drop-zone works without a mouse. Files are never re-filled after a validation error
    (browsers do not allow it), so the hint should say so when it matters.
--}}
@props(['name', 'label' => null, 'accept' => null, 'hint' => null, 'multiple' => false, 'required' => false])
@php
    $fieldId = \App\View\Components\Support\Field::id($name, $attributes->get('id'));
    $error = \App\View\Components\Support\Field::error($errors ?? null, $name);
    $describedBy = trim(($hint && ! $error ? $fieldId.'-hint ' : '').($error ? $fieldId.'-error' : ''));
    $inputName = $multiple && ! str_ends_with($name, '[]') ? $name.'[]' : $name;
@endphp
<div {{ $attributes->only('class')->class(['field']) }} x-data="fileInput({ multiple: @js((bool) $multiple) })">
    @if ($label)
        <label for="{{ $fieldId }}" @class(['label', 'label-required' => $required])>{{ $label }}</label>
    @endif

    <label for="{{ $fieldId }}" @class(['dropzone', 'is-invalid' => $error]) :class="{ 'is-dragging': dragging }"
           @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)">
        <input type="file" x-ref="input" name="{{ $inputName }}" id="{{ $fieldId }}" class="sr-only" @change="refresh()"
               {{ $attributes->except(['class', 'id']) }}
               @if ($accept) accept="{{ $accept }}" @endif @if ($multiple) multiple @endif @if ($required) required aria-required="true" @endif
               @if ($error) aria-invalid="true" @endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>

        <span class="dropzone-icon"><x-ui.icon name="arrow-up-tray" /></span>
        <span class="text-sm font-medium text-ink">
            <span class="text-brand-ink">Choose {{ $multiple ? 'files' : 'a file' }}</span>
            <span class="hidden sm:inline">or drag and drop</span>
        </span>
        <span class="text-xs text-ink-faint" x-show="files.length === 0">
            @if ($accept) {{ str_replace(',', ', ', $accept) }} @else Any file type @endif
        </span>

        <ul class="mt-2 w-full max-w-sm space-y-1 text-left" x-show="files.length > 0" x-cloak>
            <template x-for="file in files" :key="file.name">
                <li class="flex items-center justify-between gap-3 rounded-lg bg-surface px-3 py-1.5 text-xs shadow-card">
                    <span class="truncate font-medium text-ink" x-text="file.name"></span>
                    <span class="shrink-0 text-ink-faint" x-text="file.size"></span>
                </li>
            </template>
        </ul>
    </label>

    @if ($hint && ! $error)
        <p class="hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif
    <x-form.error :name="$name" :id="$fieldId" />
</div>
