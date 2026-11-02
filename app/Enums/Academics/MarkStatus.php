<?php

namespace App\Enums\Academics;

/**
 * State of one student's mark in one exam subject.
 *
 *  pending         nothing entered yet: the result is incomplete (never guessed as 0).
 *  present         marks entered.
 *  absent          sat out the paper: 0 marks, failed, counted in the total (setting absent_counts_in_total).
 *  not_applicable  the subject does not apply to this student (e.g. optional subject not taken): left out.
 *  exempt          excused by the school (e.g. medical): left out of totals and pass/fail.
 */
enum MarkStatus: string
{
    case Pending = 'pending';
    case Present = 'present';
    case Absent = 'absent';
    case NotApplicable = 'not_applicable';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not entered',
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::NotApplicable => 'Not applicable',
            self::Exempt => 'Exempt',
        };
    }

    /** Short code printed on result sheets and report cards in place of marks. */
    public function code(): string
    {
        return match ($this) {
            self::Pending => '—',
            self::Present => '',
            self::Absent => 'AB',
            self::NotApplicable => 'NA',
            self::Exempt => 'EX',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Present => 'success',
            self::Absent => 'danger',
            self::NotApplicable, self::Exempt => 'neutral',
        };
    }

    /** Excluded from totals, maximum and pass/fail. */
    public function isExcluded(): bool
    {
        return $this === self::NotApplicable || $this === self::Exempt;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
