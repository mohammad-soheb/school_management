{{--
    <x-form.date name="date_of_birth" label="Date of birth" :value="$student->date_of_birth?->format('Y-m-d')" max="{{ today()->toDateString() }}" required />
    A native date picker (type="date"); the value must be Y-m-d. Same props and behaviour as <x-form.input>.
--}}
@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'required' => false])
<x-form.input type="date" :name="$name" :label="$label" :value="$value" :hint="$hint" :required="$required" {{ $attributes }} />
