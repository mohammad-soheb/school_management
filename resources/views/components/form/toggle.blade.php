{{--
    Switch for an on/off setting. Same props and behaviour as <x-form.checkbox>.
    <x-form.toggle name="is_active" label="Active" hint="Inactive users cannot sign in." :checked="$user->is_active" />
--}}
@props(['name', 'label' => null, 'value' => 1, 'checked' => false, 'hint' => null])
@php
    $fieldKey = \App\View\Components\Support\Field::key($name);
    $fieldId = \App\View\Components\Support\Field::id($name.'-'.$value, $attributes->get('id'));
    $error = \App\View\Components\Support\Field::error($errors ?? null, $name);
    $isChecked = \App\View\Components\Support\Field::hasOldInput()
        ? in_array((string) $value, array_map('strval', \Illuminate\Support\Arr::wrap(old($fieldKey))), true)
        : (bool) $checked;
@endphp
<div {{ $attributes->only('class')->class(['field']) }}>
    <label for="{{ $fieldId }}" class="flex min-h-[2.75rem] cursor-pointer items-center justify-between gap-4 py-1">
        <span class="min-w-0">
            <span class="check-label">{{ $label }}{{ $slot }}</span>
            @if ($hint)<span class="check-hint">{{ $hint }}</span>@endif
        </span>
        <input type="checkbox" role="switch" name="{{ $name }}" id="{{ $fieldId }}" value="{{ $value }}" {{ $attributes->except(['class', 'id'])->class(['toggle']) }}
               @checked($isChecked) @if ($error) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif>
    </label>
    <x-form.error :name="$name" :id="$fieldId" />
</div>
