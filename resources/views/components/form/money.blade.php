{{--
    <x-form.money name="amount" label="Amount received" value="1250.50" required />
    <x-form.money name="amount" label="Fee amount" :paise="$feeHead->amount_paise" />      integer paise from the database

    The submitted value is rupees as a plain decimal string ("1250.50": no currency symbol, no thousands separators).
    The server converts it with Money::parse() (which also accepts "1,250.50" and a leading ₹) to integer paise (never use floats for money). Phones get the decimal keypad.
    value   rupees as a decimal string (wins over `paise`); old('name') wins over both after a failed validation.
    paise   integer paise; rendered with Money::plain() (e.g. 125050 -> "1250.50").
    prefix  defaults to the rupee sign; pass prefix="" to hide it.
--}}
@props(['name', 'label' => null, 'value' => null, 'paise' => null, 'hint' => null, 'required' => false, 'prefix' => "\u{20B9}"])
@php
    $rupees = $value ?? ($paise !== null && $paise !== '' ? \App\Support\Money\Money::plain((int) $paise) : null);
@endphp
<x-form.input type="text" :name="$name" :label="$label" :value="$rupees" :hint="$hint" :required="$required" :prefix="$prefix"
              inputmode="decimal" autocomplete="off" placeholder="0.00" input-class="tabular-nums"
              {{ $attributes }} />
