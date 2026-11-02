{{--
    Confirm-before-you-do-it. The default slot is the TRIGGER (any button / link); clicking it opens a modal whose
    confirm button submits a POST form with @csrf and @method.

    <x-ui.confirm :action="route('school.students.destroy', $student)" method="DELETE"
                  title="Remove this student?" message="Their history is kept; they will be marked inactive."
                  confirm="Remove" tone="danger">
        <x-ui.button variant="ghost" size="sm" icon="trash">Remove</x-ui.button>
    </x-ui.confirm>

    method   DELETE (default) | PUT | PATCH | POST     tone  danger (default) | warning | info | success | brand
    Esc, the backdrop and the Cancel button close it (Cancel has initial focus, so Enter never confirms by accident).
    Do not nest it inside another <form>; it renders its own (the modal is teleported to <body>, so a table cell is fine).
--}}
@props([
    'action',
    'method' => 'DELETE',
    'title' => 'Are you sure?',
    'message' => null,
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',
    'tone' => 'danger',
])
@php
    $name = 'confirm-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $formId = $name.'-form';
    $verb = strtoupper($method);
    $spoofed = in_array($verb, ['PUT', 'PATCH', 'DELETE'], true);

    $buttons = ['danger' => 'btn-danger', 'success' => 'btn-success', 'warning' => 'btn-primary', 'info' => 'btn-primary', 'brand' => 'btn-primary'];
    $iconTones = ['danger' => 'modal-icon-danger', 'success' => 'modal-icon-success', 'warning' => 'modal-icon-warning', 'info' => 'modal-icon-info', 'brand' => 'modal-icon-brand'];
    $icons = ['danger' => 'exclamation-triangle', 'success' => 'check-circle', 'warning' => 'exclamation-triangle', 'info' => 'information-circle', 'brand' => 'question-mark-circle'];
    $tone = array_key_exists($tone, $buttons) ? $tone : 'danger';
@endphp
<span class="contents" x-data="confirm({ name: @js($name) })">
    <span class="contents" @click.prevent.stop="show()">{{ $slot }}</span>

    <x-ui.modal :name="$name" max-width="sm" :show-close="false" labelledby="{{ $name }}-title" describedby="{{ $name }}-message">
        <div class="flex items-start gap-4 pt-3">
            <div class="modal-icon shrink-0 {{ $iconTones[$tone] }}"><x-ui.icon :name="$icons[$tone]" /></div>
            <div class="min-w-0 pb-2">
                <h2 class="modal-title" id="{{ $name }}-title">{{ $title }}</h2>
                @if ($message)
                    <p class="modal-description" id="{{ $name }}-message">{{ $message }}</p>
                @endif
            </div>
        </div>

        <form id="{{ $formId }}" method="POST" action="{{ $action }}">
            @csrf
            @if ($spoofed)
                @method($verb)
            @endif
        </form>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary btn-md" autofocus @click="hide()">{{ $cancel }}</button>
            <button type="submit" form="{{ $formId }}" class="btn {{ $buttons[$tone] }} btn-md">{{ $confirm }}</button>
        </x-slot:footer>
    </x-ui.modal>
</span>
