{{--
    <x-ui.button @click="$dispatch('open-modal', 'invite-user')">Invite</x-ui.button>

    <x-ui.modal name="invite-user" title="Invite a user" description="They will receive an email." max-width="lg">
        ...body (a form, text ...)...
        <x-slot:footer>
            <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'invite-user')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="invite-form">Send invite</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    name       unique per page. Open with $dispatch('open-modal', name), close with $dispatch('close-modal', name) / Esc / backdrop.
    maxWidth   sm | md | lg | xl     (a bottom sheet on phones)
    show       open on page load (e.g. :show="$errors->any()" to re-open a modal whose form failed validation)
    closeable  false disables Esc / backdrop / the X button (use sparingly)
    The dialog is teleported to <body>, so it can live inside a table cell or a form without clipping or nested forms.
    Focus is trapped while open and returns to the opener on close; give a control the `autofocus` attribute to choose
    what receives focus first.
--}}
@props(['name', 'title' => null, 'description' => null, 'maxWidth' => 'md', 'show' => false, 'closeable' => true, 'showClose' => true, 'labelledby' => null, 'describedby' => null])
@php
    $sizes = ['sm' => 'modal-sm', 'md' => 'modal-md', 'lg' => 'modal-lg', 'xl' => 'modal-xl'];
    $slug = \Illuminate\Support\Str::slug($name);
    $titleId = 'modal-title-'.$slug;
    $descriptionId = 'modal-desc-'.$slug;
    $labelId = $labelledby ?? ($title ? $titleId : null);
    $describeId = $describedby ?? ($description ? $descriptionId : null);
    $hasFooter = isset($footer) && $footer->isNotEmpty();
    $showCloseButton = $closeable && $showClose;
@endphp
<div class="contents" x-data="modal({ name: @js($name), show: @js((bool) $show) })"
     @open-modal.window="onOpen($event)" @close-modal.window="onClose($event)" @if ($closeable) @keydown.escape.window="close()" @endif>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="modal-root"
             role="dialog" aria-modal="true" @if ($labelId) aria-labelledby="{{ $labelId }}" @endif @if ($describeId) aria-describedby="{{ $describeId }}" @endif
             x-trap.noscroll.inert="open">
            <div x-show="open" class="modal-backdrop" aria-hidden="true" @if ($closeable) @click="close()" @endif
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

            <div x-show="open" {{ $attributes->class(['modal-panel', $sizes[$maxWidth] ?? $sizes['md']]) }}
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-2 sm:scale-95" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100" x-transition:leave-end="translate-y-6 opacity-0 sm:translate-y-2 sm:scale-95">
                @if ($title || $showCloseButton)
                    <div class="modal-header">
                        <div class="min-w-0">
                            @if ($title)
                                <h2 id="{{ $titleId }}" class="modal-title">{{ $title }}</h2>
                            @endif
                            @if ($description)
                                <p id="{{ $descriptionId }}" class="modal-description">{{ $description }}</p>
                            @endif
                        </div>
                        @if ($showCloseButton)
                            <button type="button" class="modal-close" @click="close()" aria-label="Close dialog">
                                <x-ui.icon name="x-mark" />
                            </button>
                        @endif
                    </div>
                @endif

                <div class="modal-body">{{ $slot }}</div>

                @if ($hasFooter)
                    <div class="modal-footer">{{ $footer }}</div>
                @endif
            </div>
        </div>
    </template>
</div>
