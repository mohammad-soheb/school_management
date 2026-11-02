<?php

namespace App\Http\Requests\Students;

use App\Models\Students\Guardian;
use Illuminate\Foundation\Http\FormRequest;

/** Invite a guardian to the parent portal (Parent role, one-time link). */
class InviteGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guardian = $this->route('guardian');

        return $guardian instanceof Guardian && $this->user()->can('invite', $guardian);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}
