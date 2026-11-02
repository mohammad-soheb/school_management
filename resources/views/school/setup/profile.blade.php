<x-layouts.app title="School profile">
    <x-ui.page-header title="School profile" description="Shown on receipts, report cards, certificates and the parent portal."
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Profile']]" />

    @include('school.setup._nav')

    <x-form.form :action="route('school.setup.profile.update')" method="PUT" :files="true" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="School details">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="name" label="School name" :value="$school->name" required maxlength="150" class="sm:col-span-2" />
                    <x-form.input name="board" label="Board / affiliation" :value="$school->board" maxlength="60" placeholder="CBSE, MP Board ..." />
                    <x-ui.dl class="self-end">
                        <x-ui.dl-item label="School code">{{ $school->code }}</x-ui.dl-item>
                    </x-ui.dl>
                </div>
            </x-ui.card>

            <x-ui.card title="Contact">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="phone" label="Phone" type="tel" :value="$school->phone" maxlength="20" autocomplete="tel" />
                    <x-form.input name="email" label="Email" type="email" :value="$school->email" maxlength="255" autocomplete="email" />
                </div>
            </x-ui.card>

            <x-ui.card title="Address">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="address_line1" label="Address line 1" :value="$school->address_line1" maxlength="255" class="sm:col-span-2" autocomplete="address-line1" />
                    <x-form.input name="address_line2" label="Address line 2" :value="$school->address_line2" maxlength="255" class="sm:col-span-2" autocomplete="address-line2" />
                    <x-form.input name="city" label="City" :value="$school->city" maxlength="80" autocomplete="address-level2" />
                    <x-form.input name="state" label="State" :value="$school->state" maxlength="80" autocomplete="address-level1" />
                    <x-form.input name="pincode" label="PIN code" :value="$school->pincode" inputmode="numeric" maxlength="6" autocomplete="postal-code" />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Logo" description="Square images look best.">
                <div class="mb-4 flex items-center gap-4">
                    @if ($logo)
                        <img src="{{ route('school.files.show', $logo) }}" alt="Current school logo"
                             class="h-20 w-20 rounded-xl border border-line bg-surface-muted object-contain p-1">
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-medium text-ink">{{ $logo->original_name }}</p>
                            <p class="text-ink-faint">{{ number_format($logo->size / 1024, 0) }} KB</p>
                        </div>
                    @else
                        <x-ui.avatar :name="$school->name" size="xl" aria-hidden="true" />
                        <p class="text-sm text-ink-faint">No logo yet. Your initials are shown instead.</p>
                    @endif
                </div>

                <x-form.file name="logo" label="Upload a new logo" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG or WebP, up to 1 MB." />

                @if ($logo)
                    <div class="mt-3">
                        <x-form.checkbox name="remove_logo" label="Remove the current logo" />
                    </div>
                @endif
            </x-ui.card>
        </div>

        <x-form.actions sticky class="lg:col-span-3">
            <x-ui.button :href="route('school.setup.index')" variant="ghost">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save profile</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.app>
