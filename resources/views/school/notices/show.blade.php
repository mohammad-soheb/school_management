@php
    $canManage = auth()->user()->can('update', $notice);
    $tz = config('app.display_timezone');
    $status = $notice->status;
    $Status = \App\Enums\Communication\NoticeStatus::class;
@endphp
<x-layouts.app :title="$notice->title">
    <x-ui.page-header :title="$notice->title"
        :breadcrumbs="[['label' => 'Notice board', 'url' => route('school.notices.index')], ['label' => $notice->title]]">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            @if ($canManage)
                <x-ui.badge :tone="$notice->stateTone()" dot>{{ $notice->stateLabel() }}</x-ui.badge>
            @endif
            @if ($notice->is_pinned)
                <x-ui.badge tone="brand">Pinned</x-ui.badge>
            @endif
            @foreach ($notice->audiences as $audience)
                <x-ui.badge :tone="$audience->type->tone()">{{ $audience->label() }}</x-ui.badge>
            @endforeach
        </div>

        <x-slot:actions>
            @if ($canManage)
                @if ($status === $Status::Archived)
                    <x-form.form :action="route('school.notices.restore', $notice)">
                        <x-ui.button type="submit" variant="secondary" icon="arrow-uturn-left">Restore as draft</x-ui.button>
                    </x-form.form>
                @else
                    <x-ui.confirm :action="route('school.notices.archive', $notice)" method="POST" tone="warning"
                                  title="Archive this notice?" message="It is removed from every board and the parent portal. You can restore it as a draft later."
                                  confirm="Archive">
                        <x-ui.button variant="ghost" icon="archive-box">Archive</x-ui.button>
                    </x-ui.confirm>
                    @if ($status === $Status::Published)
                        <x-form.form :action="route('school.notices.unpublish', $notice)">
                            <x-ui.button type="submit" variant="secondary" icon="eye-slash">Unpublish</x-ui.button>
                        </x-form.form>
                    @else
                        <x-form.form :action="route('school.notices.publish', $notice)">
                            <x-ui.button type="submit" variant="secondary" icon="megaphone">Publish</x-ui.button>
                        </x-form.form>
                    @endif
                    <x-ui.button :href="route('school.notices.edit', $notice)" icon="pencil-square">Edit</x-ui.button>
                @endif
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card>
                <div class="comms-body">{!! nl2br(e($notice->body)) !!}</div>
            </x-ui.card>

            @if ($notice->attachments->isNotEmpty())
                <x-ui.card title="Attachments" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($notice->attachments as $file)
                            <li class="flex items-center gap-3 px-4 py-3">
                                <span class="stat-icon stat-icon-neutral shrink-0"><x-ui.icon :name="$file->isImage() ? 'photo' : 'document-text'" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-ink">{{ $file->original_name }}</span>
                                    <span class="block text-xs text-ink-faint">{{ strtoupper($file->extension) }} · {{ $file->humanSize() }}</span>
                                </span>
                                <x-ui.button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('school.notices.attachments.show', [$notice, $file])">Download</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <x-ui.card title="Details">
            <x-ui.dl>
                <x-ui.dl-item label="Shows from">{{ ($notice->publish_at ?? $notice->published_at)?->timezone($tz)->format('d M Y, h:i A') }}</x-ui.dl-item>
                <x-ui.dl-item label="Hides after">{{ $notice->expires_at?->timezone($tz)->format('d M Y, h:i A') ?? 'Until archived' }}</x-ui.dl-item>
                @if ($canManage)
                    <x-ui.dl-item label="Created by">{{ $notice->creator?->name }} <span class="text-ink-faint">· {{ $notice->created_at->timezone($tz)->format('d M Y, h:i A') }}</span></x-ui.dl-item>
                    @if ($notice->updater && ! $notice->updated_at->eq($notice->created_at))
                        <x-ui.dl-item label="Last edited by">{{ $notice->updater->name }} <span class="text-ink-faint">· {{ $notice->updated_at->timezone($tz)->format('d M Y, h:i A') }}</span></x-ui.dl-item>
                    @endif
                @endif
                @if ($notice->publisher)
                    <x-ui.dl-item label="Published by">{{ $notice->publisher->name }}</x-ui.dl-item>
                @endif
            </x-ui.dl>
        </x-ui.card>
    </div>
</x-layouts.app>
