{{--
    The <html> document every layout is built on: head, <body>, toast stack, @stack('scripts').
    theme=false  -> no dark mode (print documents are always light)
    toasts=false -> no flash/toast region
    inline-status -> the page prints the `status` flash itself, so don't also toast it
--}}
@props(['title', 'bodyClass' => '', 'theme' => true, 'toasts' => true, 'inlineStatus' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-layouts.partials.head :title="$title" :theme="$theme" />
</head>
<body {{ $attributes->class([$bodyClass => filled($bodyClass)]) }}>
    {{ $slot }}

    @if ($toasts)
        <x-ui.toasts :except="$inlineStatus ? ['status'] : []" />
    @endif

    @stack('scripts')
</body>
</html>
