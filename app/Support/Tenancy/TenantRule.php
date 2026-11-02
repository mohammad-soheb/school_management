<?php

namespace App\Support\Tenancy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules that only look at the active school's rows.
 *
 *   'section_id'   => ['required', TenantRule::exists('sections')],
 *   'admission_no' => ['required', TenantRule::unique('students', 'admission_no')->ignore($student)],
 *
 * A plain Rule::exists('sections') would accept another school's section id.
 */
final class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('school_id', app(TenantContext::class)->requireId());
    }

    public static function unique(string $table, ?string $column = null): Unique
    {
        return Rule::unique($table, $column)->where('school_id', app(TenantContext::class)->requireId());
    }
}
