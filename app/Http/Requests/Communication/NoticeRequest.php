<?php

namespace App\Http\Requests\Communication;

use App\Models\Communication\Notice;
use App\Services\Communication\NoticeService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a notice. Audience rules beyond id checks (at least one target, no "parent" role) and the
 * publication window are enforced in NoticeService.
 */
class NoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $notice = $this->route('notice');

        return $notice instanceof Notice
            ? $this->user()->can('update', $notice)
            : $this->user()->can('create', Notice::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(NoticeService::AUDIENCE_CHOICES)],
            'class_ids' => ['nullable', 'array', 'required_without:role_ids', 'exclude_unless:audience,selected'],
            'class_ids.*' => ['integer', TenantRule::exists('school_classes')],
            'role_ids' => ['nullable', 'array', 'exclude_unless:audience,selected'],
            'role_ids.*' => ['integer', TenantRule::exists('roles')],
            'publish_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'is_pinned' => ['nullable', 'boolean'],
            'action' => ['nullable', Rule::in(['draft', 'publish'])],
            'attachments' => ['nullable', 'array', 'max:'.NoticeService::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'max:'.config('documents.purposes.notice.max_kb')],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'class_ids' => 'classes',
            'class_ids.*' => 'class',
            'role_ids.*' => 'role',
            'publish_at' => 'publish time',
            'expires_at' => 'expiry time',
            'attachments.*' => 'attachment',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['class_ids.required_without' => 'Choose at least one class or role.'];
    }
}
