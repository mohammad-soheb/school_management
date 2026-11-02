<x-dev.section id="forms" title="Forms" description="Single column on phones, two columns from md. Errors appear under the field; open this page with ?invalid=1 to see them.">
    <div class="grid gap-6 xl:grid-cols-3">
        <x-ui.card title="Admission form" description="Controls, hints, prefixes, validation." class="xl:col-span-2">
            <x-form.form action="{{ url('/_ui/slow') }}" method="POST" :files="true" class="space-y-8">
                <x-form.idempotency />

                <div class="form-section">
                    <div>
                        <h3 class="form-section-title">Student</h3>
                        <p class="form-section-description">Basic details as they appear on the admission register.</p>
                    </div>
                    <div class="form-grid">
                        <x-form.input name="name" label="Full name" value="Aarav Sharma" required autocomplete="off" />
                        <x-form.input name="email" type="email" label="Guardian email" value="rohit.sharma@example.test" hint="Used for fee receipts and notices." required />
                        <x-form.date name="date_of_birth" label="Date of birth" value="2015-06-14" required />
                        <x-form.select name="class_id" label="Class" placeholder="Choose a class" :options="[1 => 'Class 4', 2 => 'Class 5', 3 => 'Class 6']" selected="2" required />
                        <x-form.input name="weight" label="Weight" suffix="kg" inputmode="decimal" value="31.5" />
                        <x-form.money name="amount" label="Admission fee" value="1250.50" hint="Plain rupees; the server converts to paise." />
                        <x-form.select name="roles[]" label="Activities (multiple)" :options="['cricket' => 'Cricket', 'music' => 'Music', 'chess' => 'Chess', 'art' => 'Art']" :selected="['music']" multiple size="4" class="md:col-span-2" />
                        <x-form.textarea name="remarks" label="Remarks" rows="3" hint="Printed on the admission slip." class="md:col-span-2">Moved from Pune.</x-form.textarea>
                    </div>
                </div>

                <div class="form-section">
                    <div>
                        <h3 class="form-section-title">Options</h3>
                    </div>
                    <div class="grid gap-x-8 gap-y-1 md:grid-cols-2">
                        <fieldset>
                            <legend class="label">Gender</legend>
                            <x-form.radio name="gender" value="female" label="Female" />
                            <x-form.radio name="gender" value="male" label="Male" checked />
                            <x-form.radio name="gender" value="other" label="Prefer not to say" />
                        </fieldset>
                        <fieldset>
                            <legend class="label">Transport and notices</legend>
                            <x-form.checkbox name="uses_bus" label="Uses school bus" hint="Adds the transport fee head." checked />
                            <x-form.checkbox name="sms" label="Send SMS receipts" />
                            <x-form.toggle name="is_active" label="Active student" hint="Inactive students stay in history." checked />
                        </fieldset>
                    </div>
                </div>

                <div class="form-section">
                    <x-form.file name="photo" label="Student photo" accept="image/jpeg,image/png" hint="JPG or PNG, up to 2 MB." />
                </div>

                <x-form.actions sticky>
                    <x-ui.button variant="ghost" href="#forms">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="check">Save student</x-ui.button>
                </x-form.actions>
            </x-form.form>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card title="Fee entry" description="Student lookup (type 2+ letters: aar, 1210, 5-A).">
                <div class="space-y-5" x-data="{ picked: null }" @lookup-selected="picked = $event.detail" @lookup-cleared="picked = null">
                    <x-form.lookup name="student_id" label="Student" :url="url('/_ui/lookup')" placeholder="Admission no. or name" required hint="Search by admission number or name." />
                    <div x-show="picked" x-cloak class="rounded-xl border border-line bg-surface-muted p-3 text-sm">
                        <p class="font-medium text-ink">Selected: <span x-text="picked && picked.label"></span></p>
                        <p class="text-ink-soft" x-text="picked && picked.sublabel"></p>
                    </div>
                    <x-form.money name="paying" label="Amount received" />
                    <x-form.password name="demo_password" label="Password field" autocomplete="new-password" />
                </div>
            </x-ui.card>

            <x-ui.card title="Standalone pieces">
                <x-form.field label="Custom control" name="custom" hint="Any control inside form.field gets label, hint and error.">
                    <input id="f-custom" name="custom" class="input" placeholder="Anything">
                </x-form.field>
                <div class="mt-5 flex items-center gap-3 text-sm text-ink-soft">
                    <x-ui.copy text="ADM-1042" size="sm">Copy admission no.</x-ui.copy>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-dev.section>
