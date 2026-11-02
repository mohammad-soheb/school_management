<?php

namespace App\Http\Requests\Students;

use App\Enums\Students\GuardianRelation;
use App\Models\Students\StudentGuardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Change a link's relation and primary / emergency-contact flags. */
class UpdateGuardianLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $link = $this->route('guardianLink');

        return $link instanceof StudentGuardian && $this->user()->can('update', $link);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'relation' => ['required', Rule::enum(GuardianRelation::class)],
            'is_primary' => ['nullable', 'boolean'],
            'is_emergency_contact' => ['nullable', 'boolean'],
        ];
    }
}
