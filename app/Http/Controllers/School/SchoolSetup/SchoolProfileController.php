<?php

namespace App\Http\Controllers\School\SchoolSetup;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolSetup\UpdateSchoolProfileRequest;
use App\Services\SchoolSetup\SchoolProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** The school's own profile and logo (FR-201). */
class SchoolProfileController extends Controller
{
    public function __construct(private readonly SchoolProfileService $profile) {}

    public function edit(): View
    {
        return view('school.setup.profile', [
            'school' => $this->profile->school(),
            'logo' => $this->profile->logo(),
        ]);
    }

    public function update(UpdateSchoolProfileRequest $request): RedirectResponse
    {
        $this->profile->update(
            $request->safe()->only(SchoolProfileService::FIELDS),
            $request->file('logo'),
            $request->boolean('remove_logo'),
            $request->user(),
        );

        return redirect()->route('school.setup.profile.edit')->with('status', 'School profile saved.');
    }
}
