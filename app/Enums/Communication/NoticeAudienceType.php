<?php

namespace App\Enums\Communication;

/**
 * One row of a notice's audience (a notice has one or more rows; it reaches the union of them).
 *   school  - everyone in the school: all staff and all parents
 *   staff   - every staff member (anyone in the school area), not parents
 *   parents - every parent / guardian (parent portal), not staff
 *   class   - parents of students enrolled in that class, and staff assigned to teach it
 *   role    - every staff member holding that role (e.g. Teacher, Accountant)
 */
enum NoticeAudienceType: string
{
    case School = 'school';
    case Staff = 'staff';
    case Parents = 'parents';
    case SchoolClass = 'class';
    case Role = 'role';

    public function label(): string
    {
        return match ($this) {
            self::School => 'Whole school',
            self::Staff => 'All staff',
            self::Parents => 'All parents',
            self::SchoolClass => 'Class',
            self::Role => 'Role',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::School => 'brand',
            self::Staff => 'info',
            self::Parents => 'success',
            self::SchoolClass => 'warning',
            self::Role => 'neutral',
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
}
