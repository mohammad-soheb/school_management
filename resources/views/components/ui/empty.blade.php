{{--
    <x-ui.empty icon="user-group" title="No students yet" description="Admit your first student to get started.">
        <x-ui.button href="..." icon="plus">Admit student</x-ui.button>
    </x-ui.empty>
    The default slot holds the action buttons.
--}}
@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'description' => null])
<div {{ $attributes->class(['empty']) }}>
    <div class="empty-icon"><x-ui.icon :name="$icon" /></div>
    <h3 class="empty-title">{{ $title }}</h3>
    @if ($description)
        <p class="empty-description">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="empty-actions">{{ $slot }}</div>
    @endif
</div>
