{{-- Tabs across the school setup screens. Shows only the screens the user may open. --}}
@php
    $setupUser = auth()->user();
    $setupTabs = array_values(array_filter([
        ['label' => 'Overview', 'url' => route('school.setup.index'), 'active' => request()->routeIs('school.setup.index'), 'icon' => 'building-library'],
        ['label' => 'Academic years', 'url' => route('school.setup.years.index'), 'active' => request()->routeIs('school.setup.years.*'), 'icon' => 'calendar'],
        ['label' => 'Classes', 'url' => route('school.setup.classes.index'), 'active' => request()->routeIs('school.setup.classes.*', 'school.setup.sections.*'), 'icon' => 'rectangle-group'],
        ['label' => 'Subjects', 'url' => route('school.setup.subjects.index'), 'active' => request()->routeIs('school.setup.subjects.*'), 'icon' => 'book-open'],
        ['label' => 'Calendar', 'url' => route('school.setup.holidays.index'), 'active' => request()->routeIs('school.setup.holidays.*'), 'icon' => 'calendar-days'],
        $setupUser->can('setup.profile.update') ? ['label' => 'Profile', 'url' => route('school.setup.profile.edit'), 'active' => request()->routeIs('school.setup.profile.*'), 'icon' => 'identification'] : null,
        $setupUser->can('setup.settings.manage') ? ['label' => 'Settings', 'url' => route('school.setup.settings.edit'), 'active' => request()->routeIs('school.setup.settings.*'), 'icon' => 'cog-6-tooth'] : null,
    ]));
@endphp
<x-ui.tabs :tabs="$setupTabs" label="School setup sections" class="mb-6" />
