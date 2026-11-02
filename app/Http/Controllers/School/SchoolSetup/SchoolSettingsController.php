<?php

namespace App\Http\Controllers\School\SchoolSetup;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolSetup\UpdateSchoolSettingsRequest;
use App\Services\SchoolSetup\SchoolSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Per-school rules: attendance, receipts, security and optional modules (FR-201 "basic settings"). */
class SchoolSettingsController extends Controller
{
    public function __construct(private readonly SchoolSettingsService $settings) {}

    public function edit(): View
    {
        return view('school.setup.settings', [
            'values' => $this->settings->current(),
            'roles' => $this->settings->roleOptions(),
        ]);
    }

    public function update(UpdateSchoolSettingsRequest $request): RedirectResponse
    {
        $changed = $this->settings->update($request->validated(), $request->user());

        return redirect()->route('school.setup.settings.edit')
            ->with($changed === [] ? 'info' : 'status', $changed === [] ? 'Nothing changed.' : 'Settings saved.');
    }
}
