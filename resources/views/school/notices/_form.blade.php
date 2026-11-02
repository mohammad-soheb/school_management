{{--
    Notice form (create + edit). Receives $notice (nullable), $state (NoticeService::formState), $classes, $roles.
--}}
@php
    $audience = (string) old('audience', $state['audience']);
    $audienceChoices = [
        'school' => ['Whole school', 'All staff and all parents', 'building-library'],
        'staff' => ['All staff', 'Everyone who signs in to the school area', 'briefcase'],
        'parents' => ['All parents', 'Parents and guardians on the parent portal', 'user-group'],
        'selected' => ['Selected classes or roles', 'Parents and teachers of chosen classes, or chosen staff roles', 'adjustments-horizontal'],
    ];
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-ui.card title="Notice">
            <div class="space-y-4">
                <x-form.input name="title" label="Title" :value="$notice?->title" maxlength="200" required placeholder="e.g. Parent-teacher meeting on Saturday" />
                <x-form.textarea name="body" label="Message" :value="$notice?->body" rows="10" maxlength="10000" required
                                 hint="Plain text. Line breaks are kept; formatting and links are not." />
            </div>
        </x-ui.card>

        <x-ui.card title="Who should see it?" x-data="{ audience: @js($audience) }">
            <fieldset>
                <legend class="sr-only">Audience</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($audienceChoices as $value => [$label, $hint, $icon])
                        <label class="comms-choice" :class="{ 'is-selected': audience === @js($value) }">
                            <input type="radio" name="audience" value="{{ $value }}" class="check-input" x-model="audience" @checked($audience === $value)>
                            <x-ui.icon :name="$icon" class="h-5 w-5 shrink-0 text-ink-faint" />
                            <span class="min-w-0">
                                <span class="block font-medium text-ink">{{ $label }}</span>
                                <span class="block text-xs text-ink-faint">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-form.error name="audience" />
            </fieldset>

            <div class="mt-5 grid gap-6 sm:grid-cols-2" x-show="audience === 'selected'" x-cloak>
                <x-form.field label="Classes" name="class_ids" hint="Parents of enrolled students and teachers of these classes.">
                    <div class="comms-checklist">
                        @forelse ($classes as $id => $name)
                            <x-form.checkbox name="class_ids[]" :value="$id" :label="$name" :checked="in_array($id, $state['class_ids'], true)" />
                        @empty
                            <p class="text-sm text-ink-faint">No classes set up yet.</p>
                        @endforelse
                    </div>
                </x-form.field>
                <x-form.field label="Staff roles" name="role_ids" hint="Everyone holding the role.">
                    <div class="comms-checklist">
                        @foreach ($roles as $id => $name)
                            <x-form.checkbox name="role_ids[]" :value="$id" :label="$name" :checked="in_array($id, $state['role_ids'], true)" />
                        @endforeach
                    </div>
                </x-form.field>
            </div>
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card title="Schedule" description="Times are school time (IST). Leave empty to show it as soon as it is published, until archived.">
            <div class="space-y-4">
                <x-form.input type="datetime-local" name="publish_at" label="Show from" :value="$state['publish_at']" />
                <x-form.input type="datetime-local" name="expires_at" label="Hide after" :value="$state['expires_at']" hint="Expired notices drop off the board automatically." />
                <x-form.toggle name="is_pinned" label="Pin to the top" hint="Pinned notices stay above newer ones." :checked="(bool) $notice?->is_pinned" />
            </div>
        </x-ui.card>

        <x-ui.card title="Attachments" :description="'Optional. Up to '.\App\Services\Communication\NoticeService::MAX_ATTACHMENTS.' files, 5 MB each.'">
            @if ($notice && $notice->attachments->isNotEmpty())
                <ul class="comms-files mb-4">
                    @foreach ($notice->attachments as $file)
                        <li class="comms-file">
                            <x-ui.icon name="paper-clip" class="h-4 w-4 shrink-0 text-ink-faint" />
                            <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $file->original_name }}</span>
                            <x-form.checkbox name="remove_attachments[]" :value="$file->id" label="Remove" class="!mb-0" />
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-form.file name="attachments" multiple accept=".pdf,.jpg,.jpeg,.png" hint="PDF, JPG or PNG. Choose the files again if the form shows an error." />
        </x-ui.card>
    </div>
</div>
