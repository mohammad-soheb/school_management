{{--
    School area layout (sidebar + topbar). Data comes from App\View\Components\Layouts\App.

    <x-layouts.app :title="'Students'">
        <x-ui.page-header title="Students" />
        ...
    </x-layouts.app>

    Pages can @push('styles') / @push('scripts'). Optional props: :menu (override the sidebar), :user (override the account).
--}}
<x-layouts.partials.document :title="$title" body-class="app-body">
    <x-layouts.partials.shell
        :menu="$menu"
        :user="$user"
        :brand="$brand"
        :memberships="$memberships"
        :switch-url="$switchUrl"
        :select-url="$selectUrl"
        :academic-year="$academicYear"
        :search-url="$searchUrl"
        :profile-url="$profileUrl"
        :security-url="$securityUrl"
        :logout-url="$logoutUrl">
        {{ $slot }}
    </x-layouts.partials.shell>
</x-layouts.partials.document>
