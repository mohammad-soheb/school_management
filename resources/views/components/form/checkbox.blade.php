{{--
    <x-form.checkbox name="send_sms" label="Send an SMS receipt" hint="Charges may apply." :checked="$school->sms_enabled" />
    <x-form.checkbox name="roles[]" value="teacher" label="Teacher" />          arrays: one box per value

    * value is submitted when ticked (default 1). Unticked boxes submit nothing, so read booleans with $request->boolean('send_sms').
    * After a failed validation the box mirrors what the user ticked (old input), otherwise `checked` decides.
    * The whole row is the click target (>= 44px tall on touch screens).
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
    <label for="{{ $fieldId }}" class="check">
        <input type="checkbox" name="{{ $name }}" id="{{ $fieldId }}" value="{{ $value }}" {{ $attributes->except(['class', 'id'])->class(['check-input']) }}
               @checked($isChecked) @if ($error) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif>
        @if ($label || $hint || $slot->isNotEmpty())
            <span class="min-w-0">
                <span class="check-label">{{ $label }}{{ $slot }}</span>
                @if ($hint)<span class="check-hint">{{ $hint }}</span>@endif
            </span>
        @endif
    </label>
    <x-form.error :name="$name" :id="$fieldId" />
</div>
