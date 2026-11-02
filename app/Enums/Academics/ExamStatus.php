<?php

namespace App\Enums\Academics;

/**
 * Exam workflow: draft -> marks_open -> published -> locked.
 *
 *  draft       exam set-up (classes, subjects, max marks) can change; no marks entry.
 *  marks_open  teachers enter marks; set-up is frozen (re-open set-up to change it).
 *  published   at least one class has an active result publication (other classes may still be entering marks).
 *  locked      final: no marks entry, no publish/unpublish. Published results stay visible.
 */
enum ExamStatus: string
{
    case Draft = 'draft';
    case MarksOpen = 'marks_open';
    case Published = 'published';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::MarksOpen => 'Marks entry open',
            self::Published => 'Published',
            self::Locked => 'Locked',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::MarksOpen => 'info',
            self::Published => 'success',
            self::Locked => 'brand',
        };
    }

    /** Marks may be typed in (per class, unless that class is published). */
    public function acceptsMarks(): bool
    {
        return $this === self::MarksOpen || $this === self::Published;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
