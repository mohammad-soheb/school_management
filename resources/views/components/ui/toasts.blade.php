{{--
    Toast stack + flash messages. Every layout renders it once; pages never need to.

    Session keys it shows:   status -> success   success -> success   error -> danger   warning -> warning   info -> info
    plus a danger toast "Please fix the highlighted fields" when the view has validation errors.

    from a controller:   return back()->with('success', 'Receipt #1042 saved.');
    from JavaScript:     window.toast('Copied', 'success')   or   window.dispatchEvent(new CustomEvent('toast', { detail: { tone, message, title } }))

    except: session keys to skip (the guest layout skips `status` on pages that print it inline).
--}}
@props(['except' => []])
@php
    $map = ['status' => 'success', 'success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $items = [];

    foreach ($map as $key => $tone) {
        if (in_array($key, $except, true)) {
            continue;
        }

        foreach (\Illuminate\Support\Arr::wrap(session($key)) as $message) {
            if (! is_string($message) || trim($message) === '') {
                continue;
            }

            // Fortify flashes keys such as "password-updated": make them readable.
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/', $message)) {
                $message = ucfirst(str_replace('-', ' ', $message)).'.';
            }

            $items[] = ['tone' => $tone, 'message' => $message];
        }
    }

    $errorBag = $errors ?? null;
    if ($errorBag !== null && $errorBag->any() && ! in_array('errors', $except, true)) {
        $items[] = ['tone' => 'danger', 'message' => 'Please fix the highlighted fields'];
    }
@endphp
<div class="toast-region" x-data="toastStack(@js($items))" @toast.window="push($event.detail)" role="region" aria-label="Notifications" aria-live="polite">
    <template x-for="toast in items" :key="toast.id">
        <div class="toast" :class="toast.cls" :role="toast.role" @mouseenter="pause(toast.id)" @mouseleave="resume(toast.id)" @focusin="pause(toast.id)"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2">
            <span class="toast-icon">
                <x-ui.icon name="check" variant="s" x-show="toast.tone === 'success'" />
                <x-ui.icon name="information-circle" variant="s" x-show="toast.tone === 'info'" />
                <x-ui.icon name="exclamation-triangle" variant="s" x-show="toast.tone === 'warning'" />
                <x-ui.icon name="x-mark" variant="s" x-show="toast.tone === 'danger'" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="toast-title" x-show="toast.title" x-text="toast.title"></p>
                <p class="toast-message" x-text="toast.message"></p>
            </div>
            <button type="button" class="toast-close" @click="dismiss(toast.id)" aria-label="Dismiss notification">
                <x-ui.icon name="x-mark" />
            </button>
        </div>
    </template>
</div>

@if (count($items) > 0)
    <noscript>
        <div class="toast-region">
            @foreach ($items as $item)
                <div class="toast toast-{{ $item['tone'] }}" role="status">
                    <div class="min-w-0 flex-1"><p class="toast-message">{{ $item['message'] }}</p></div>
                </div>
            @endforeach
        </div>
    </noscript>
@endif
