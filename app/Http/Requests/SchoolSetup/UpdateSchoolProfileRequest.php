<?php

namespace App\Http\Requests\SchoolSetup;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setup.profile.update');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'board' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            // The file's real content type is checked again by PrivateFileService (purpose school_logo).
            'logo' => ['nullable', 'file', 'max:1024', 'mimes:jpg,jpeg,png,webp'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number (digits, spaces, + and - only).',
            'pincode.regex' => 'Enter a valid 6-digit PIN code.',
            'logo.max' => 'The logo must be 1 MB or smaller.',
            'logo.mimes' => 'The logo must be a JPG, PNG or WebP image.',
        ];
    }
}
