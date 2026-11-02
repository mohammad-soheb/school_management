<x-layouts.guest title="Choose a school" heading="Choose a school" subheading="You belong to more than one school. Pick the one you want to work in.">
    <ul class="space-y-3">
        @foreach ($memberships as $membership)
            <li>
                <x-form.form :action="route('school.switch')" class="flex items-center justify-between gap-3 rounded-2xl border border-line bg-surface p-4">
                    <input type="hidden" name="school_id" value="{{ $membership->school_id }}">

                    <div class="flex min-w-0 items-center gap-3">
                        <x-ui.avatar :name="$membership->school->name" />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink">{{ $membership->school->name }}</p>
                            <p class="truncate text-sm text-ink-soft">{{ $membership->roles->pluck('name')->join(', ') }}</p>
                        </div>
                    </div>

                    <x-ui.button type="submit" :variant="$membership->school_id == $currentSchoolId ? 'secondary' : 'primary'" size="sm">
                        {{ $membership->school_id == $currentSchoolId ? 'Current' : 'Open' }}
                    </x-ui.button>
                </x-form.form>
            </li>
        @endforeach
    </ul>

    @error('school_id')
        <p class="field-error mt-4" role="alert">{{ $message }}</p>
    @enderror

    <x-form.form :action="route('logout')" class="mt-6 text-center">
        <button type="submit" class="text-sm text-ink-soft underline-offset-4 hover:underline">Sign out</button>
    </x-form.form>
</x-layouts.guest>
