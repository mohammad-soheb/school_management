@php $school ??= null; @endphp
<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="name" label="School name" :value="$school?->name" required class="sm:col-span-2" />
    @unless ($school)
        <x-form.input name="code" label="Short code" hint="Lower-case letters, numbers and dashes. Cannot be changed later." required />
    @endunless
    <x-form.input name="board" label="Board" :value="$school?->board" placeholder="CBSE, MP Board, ICSE…" />
    <x-form.input name="email" label="Office email" type="email" :value="$school?->email" />
    <x-form.input name="phone" label="Office phone" type="tel" :value="$school?->phone" />
    <x-form.input name="address_line1" label="Address" :value="$school?->address_line1" class="sm:col-span-2" />
    <x-form.input name="address_line2" label="Address line 2" :value="$school?->address_line2" class="sm:col-span-2" />
    <x-form.input name="city" label="City" :value="$school?->city ?? 'Indore'" />
    <x-form.input name="state" label="State" :value="$school?->state ?? 'Madhya Pradesh'" />
    <x-form.input name="pincode" label="PIN code" inputmode="numeric" :value="$school?->pincode" />
</div>
