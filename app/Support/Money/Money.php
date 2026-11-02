<?php

namespace App\Support\Money;

/**
 * Money is stored everywhere as integer paise (never floats, FR-412). These helpers convert at the edges only.
 */
final class Money
{
    /**
     * Parse an amount typed by a person ("1250", "1,250.50", "₹ 99.5", "Rs. 10") into integer paise.
     * Returns null for anything that is not a plain amount with at most two decimals.
     */
    public static function parse(string|int|null $input): ?int
    {
        if ($input === null) {
            return null;
        }

        if (is_int($input)) {
            return $input * 100;
        }

        $clean = preg_replace('/^(₹|rs\.?|inr)|[\s,]/iu', '', trim($input));

        if ($clean === null || ! preg_match('/^(-)?(\d{1,12})(?:\.(\d{1,2}))?$/', $clean, $m)) {
            return null;
        }

        $paise = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '', 2, '0');

        return $m[1] === '-' ? -$paise : $paise;
    }

    /** "₹1,23,456.50" with Indian digit grouping. Whole-rupee amounts omit the decimals unless $decimals is true. */
    public static function format(int $paise, bool $symbol = true, bool $decimals = true): string
    {
        $negative = $paise < 0;
        $paise = abs($paise);
        $rupees = intdiv($paise, 100);
        $fraction = $paise % 100;

        $digits = (string) $rupees;
        if (strlen($digits) > 3) {
            $last3 = substr($digits, -3);
            $rest = substr($digits, 0, -3);
            $digits = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
        }

        $out = $digits;
        if ($decimals || $fraction !== 0) {
            $out .= '.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
        }

        return ($negative ? '-' : '').($symbol ? '₹' : '').$out;
    }

    /** Plain decimal string for form inputs and exports: 125050 -> "1250.50". */
    public static function plain(int $paise): string
    {
        return ($paise < 0 ? '-' : '').intdiv(abs($paise), 100).'.'.str_pad((string) (abs($paise) % 100), 2, '0', STR_PAD_LEFT);
    }
}
