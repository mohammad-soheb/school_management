{{--
    Hidden idempotency_key for endpoints that must never run twice (payment posting, imports, year rollover).
    <x-form.idempotency />      ->  <input type="hidden" name="idempotency_key" value="{uuid}">

    A fresh UUID is generated every time the form is rendered, so a browser retry / double click re-sends the SAME key
    (the server returns the first result) while opening the form again produces a new one. The server must enforce it
    with a unique constraint; this is not a substitute for that.
--}}
@props(['name' => 'idempotency_key'])
<input type="hidden" name="{{ $name }}" value="{{ \Illuminate\Support\Str::uuid() }}">
