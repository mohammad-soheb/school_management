{{--
    Platform (product owner) backoffice layout: the school shell with a "Platform" badge and the platform menu.
    <x-layouts.platform :title="'Schools'"> ... </x-layouts.platform>
--}}
<x-layouts.partials.document :title="$title" body-class="app-body">
    <x-layouts.partials.shell
        :menu="$menu"
        :user="$user"
        :brand="$brand"
        :profile-url="$profileUrl"
        :security-url="$securityUrl"
        :logout-url="$logoutUrl">
        {{ $slot }}
    </x-layouts.partials.shell>
</x-layouts.partials.document>
