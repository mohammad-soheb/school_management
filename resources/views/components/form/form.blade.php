{{--
    <x-form.form :action="route('school.students.store')" method="POST" :files="true" class="space-y-6">
        ...fields...
        <x-form.actions><x-ui.button type="submit">Save</x-ui.button></x-form.actions>
    </x-form.form>

    method  POST (default) | PUT | PATCH | DELETE | GET.  PUT/PATCH/DELETE are sent as POST with a hidden _method;
            GET forms get no CSRF token. @csrf is always included for non-GET forms.
    files   adds enctype="multipart/form-data".
    Double submits are blocked automatically (resources/js/core/submit-lock.js); opt out with data-lock="false".
--}}
@props(['action' => null, 'method' => 'POST', 'files' => false])
@php
    $verb = strtoupper($method);
    $spoofed = in_array($verb, ['PUT', 'PATCH', 'DELETE'], true);
    $httpMethod = $verb === 'GET' ? 'GET' : 'POST';
@endphp
<form method="{{ $httpMethod }}" @if ($action) action="{{ $action }}" @endif @if ($files) enctype="multipart/form-data" @endif {{ $attributes }}>
    @if ($httpMethod !== 'GET')
        @csrf
    @endif
    @if ($spoofed)
        @method($verb)
    @endif
    {{ $slot }}
</form>
