<?php

namespace App\Enums\Promotion;

enum PromotionItemState: string
{
    /** Decided in the draft, not yet committed. */
    case Pending = 'pending';
    /** Committed: the source enrollment is closed (and a target enrollment exists for promote/retain). */
    case Applied = 'applied';
    /** Left untouched (see skip_reason). */
    case Skipped = 'skipped';
    /** Committed, then undone by an authorised correction. */
    case Reverted = 'reverted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Applied => 'Applied',
            self::Skipped => 'Skipped',
            self::Reverted => 'Reverted',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Applied => 'success',
            self::Skipped => 'warning',
            self::Reverted => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** Human text for a skip_reason code. */
    public static function skipReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'student_inactive' => 'Student is not active',
            'already_enrolled' => 'Already enrolled in the target year',
            'source_changed' => 'Enrollment changed since the draft was prepared',
            default => 'Skipped',
        };
    }
}
