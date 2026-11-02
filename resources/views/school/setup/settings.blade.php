@use('App\Services\SchoolSetup\SchoolSettingsService', 'S')
@php
    $weeklyOff = array_map('intval', (array) old('attendance.weekly_off', $values['attendance.weekly_off']));
    $twoFactor = (array) old('security.require_two_factor_for', $values['security.require_two_factor_for']);
@endphp
<x-layouts.app title="School settings">
    <x-ui.page-header title="School settings" description="Rules this school works by. Every change is recorded in the audit log."
                      :breadcrumbs="[['label' => 'School setup', 'url' => route('school.setup.index')], ['label' => 'Settings']]" />

    @include('school.setup._nav')

    <x-form.form :action="route('school.setup.settings.update')" method="PUT" class="space-y-6">
        <x-ui.card title="Attendance" description="How the attendance percentage is worked out, and which days the school is closed every week.">
            <div class="grid gap-6 md:grid-cols-2">
                <fieldset>
                    <legend class="label">Approved leave</legend>
                    <div class="mt-2 space-y-1">
                        @foreach (S::LEAVE_TREATMENTS as $value => $label)
                            <x-form.radio name="attendance[leave_counts_as]" :value="$value" :label="$label" :checked="$values['attendance.leave_counts_as'] === $value" />
                        @endforeach
                    </div>
                    <x-form.error name="attendance.leave_counts_as" />
                </fieldset>

                <div class="space-y-5">
                    <x-form.input name="attendance[half_day_weight]" label="A half day counts as" type="number" step="0.05" min="0" max="1"
                                  :value="$values['attendance.half_day_weight']" suffix="day" required
                                  hint="0.5 means two half days equal one full day present." />
                    <x-form.toggle name="attendance[late_counts_as_present]" label="Late counts as present"
                                   :checked="$values['attendance.late_counts_as_present']" hint="Off: a late arrival does not add to days present." />
                </div>

                <fieldset class="md:col-span-2">
                    <legend class="label">Weekly off</legend>
                    <p class="mb-2 text-sm text-ink-faint">These days are never working days (attendance cannot be taken on them).</p>
                    <div class="grid grid-cols-2 gap-1 sm:grid-cols-4 lg:grid-cols-7">
                        @foreach (S::WEEKDAYS as $day => $label)
                            <x-form.checkbox name="attendance[weekly_off][]" :value="$day" :label="$label" :checked="in_array($day, $weeklyOff, true)" />
                        @endforeach
                    </div>
                    <x-form.error name="attendance.weekly_off" />
                </fieldset>
            </div>
        </x-ui.card>

        <x-ui.card title="Fee receipts" description="Receipt numbers and how a payment is applied to dues.">
            <x-ui.alert tone="warning" class="mb-5">Change receipt numbering only at the start of a year: receipts already issued keep their numbers.</x-ui.alert>
            <div class="grid gap-5 md:grid-cols-3">
                <x-form.input name="fees[receipt_prefix]" label="Receipt prefix" :value="$values['fees.receipt_prefix']" maxlength="10" required
                              input-class="uppercase" hint="Capital letters, digits, hyphens." />
                <x-form.input name="fees[receipt_padding]" label="Number digits" type="number" min="3" max="10"
                              :value="$values['fees.receipt_padding']" required :hint="'e.g. '.str_pad('42', (int) $values['fees.receipt_padding'], '0', STR_PAD_LEFT)" />
                <x-form.select name="fees[receipt_scope]" label="Numbering" :options="S::RECEIPT_SCOPES" :selected="$values['fees.receipt_scope']" required />
                <x-form.select name="fees[allocation_order]" label="Apply payments to" :options="S::ALLOCATION_ORDERS" :selected="$values['fees.allocation_order']" required class="md:col-span-3" />
            </div>
        </x-ui.card>

        <x-ui.card title="Security" description="Require two-factor authentication (an authenticator app code) for these roles.">
            @if ($roles === [])
                <p class="text-sm text-ink-faint">No staff roles found.</p>
            @else
                <div class="grid gap-1 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($roles as $slug => $name)
                        <x-form.checkbox name="security[require_two_factor_for][]" :value="$slug" :label="$name" :checked="in_array($slug, $twoFactor, true)" />
                    @endforeach
                </div>
                <x-form.error name="security.require_two_factor_for" />
                <p class="mt-3 text-sm text-ink-faint">Recommended for School Admin and Accountant. Members of these roles are asked to set it up at their next sign-in.</p>
            @endif
        </x-ui.card>

        <x-ui.card title="Optional modules" description="Switch on modules your school uses.">
            <div class="grid gap-4 md:grid-cols-3">
                @foreach (S::FEATURES as $key => [$label, $hint])
                    <x-form.toggle :name="'features['.$key.']'" :label="$label" :hint="$hint" :checked="$values['features.'.$key]" />
                @endforeach
            </div>
        </x-ui.card>

        <x-form.actions sticky>
            <x-ui.button :href="route('school.setup.index')" variant="ghost">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save settings</x-ui.button>
        </x-form.actions>
    </x-form.form>
</x-layouts.app>
