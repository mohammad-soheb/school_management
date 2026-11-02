<?php

namespace App\Enums\Attendance;

/** One day's status for a student or staff member (FR-301, FR-302). */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';
    case HalfDay = 'half_day';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::Leave => 'Leave',
            self::HalfDay => 'Half day',
        };
    }

    /** Register code (monthly register, CSV). */
    public function code(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Absent => 'A',
            self::Late => 'L',
            self::Leave => 'LV',
            self::HalfDay => 'HD',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::Leave => 'info',
            self::HalfDay => 'brand',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
