<x-dev.section id="buttons" title="Buttons" description="Five variants, three sizes, icons, loading and disabled states. 44px minimum on touch screens.">
    <div class="space-y-6">
        <x-ui.card title="Variants">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button variant="primary">Primary</x-ui.button>
                <x-ui.button variant="secondary">Secondary</x-ui.button>
                <x-ui.button variant="ghost">Ghost</x-ui.button>
                <x-ui.button variant="danger">Danger</x-ui.button>
                <x-ui.button variant="success">Success</x-ui.button>
                <x-ui.button variant="primary" disabled>Disabled</x-ui.button>
                <x-ui.button variant="secondary" disabled>Disabled</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card title="Sizes and icons">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button size="sm" icon="plus">Small</x-ui.button>
                <x-ui.button size="md" icon="plus">Medium</x-ui.button>
                <x-ui.button size="lg" icon="plus">Large (portal)</x-ui.button>
                <x-ui.button variant="secondary" size="md" icon="arrow-down-tray">Export</x-ui.button>
                <x-ui.button variant="secondary" icon-right="arrow-right">Continue</x-ui.button>
                <x-ui.button variant="ghost" icon="trash" class="btn-icon" aria-label="Delete" />
                <x-ui.button variant="secondary" icon="ellipsis-horizontal" class="btn-icon" aria-label="More" />
                <x-ui.button href="#buttons" variant="secondary" icon="link">As a link</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card title="Loading and block">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button class="is-loading" disabled>Saving…</x-ui.button>
                <x-ui.button variant="secondary" class="is-loading" disabled>Saving…</x-ui.button>
                <x-ui.button variant="danger" class="is-loading" disabled>Deleting…</x-ui.button>
            </div>
            <p class="hint mb-3 mt-3">Forms lock themselves on submit: the clicked button gets <code>is-loading</code> automatically. This demo posts to a 1.5 second dev endpoint; double-click it and only one request is sent.</p>
            <x-form.form action="{{ url('/_ui/slow') }}" method="POST" class="flex max-w-sm flex-col gap-3">
                <x-ui.button type="submit" block>Save (locks after the first submit)</x-ui.button>
            </x-form.form>
        </x-ui.card>
    </div>
</x-dev.section>
