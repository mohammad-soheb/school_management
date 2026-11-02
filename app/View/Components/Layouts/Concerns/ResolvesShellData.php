<?php

namespace App\View\Components\Layouts\Concerns;

use App\Support\Navigation\Menu;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * Data every application layout needs, gathered defensively so the layouts also render for a guest,
 * without a school in context, and before the routes of a module exist (the dev gallery, early pages, tests).
 */
trait ResolvesShellData
{
    protected function currentUser(): ?Authenticatable
    {
        return auth()->user();
    }

    protected function currentSchool(): ?object
    {
        return app(TenantContext::class)->school();
    }

    /** URL of a named route, or null when that route is not registered (yet). */
    protected function routeUrl(string $name, array $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }

    /**
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    protected function menuFor(string $area): array
    {
        return app(Menu::class)->build($area);
    }

    /**
     * Identity shown at the top of the sidebar / portal bar.
     *
     * @return array{name: string, sub: ?string, logo: ?string, badge: ?string}
     */
    protected function schoolBrand(?object $school): array
    {
        return [
            'name' => $school !== null ? (string) data_get($school, 'name') : (string) config('app.name'),
            'sub' => $school !== null ? data_get($school, 'code') : null,
            'logo' => $school !== null && method_exists($school, 'logoUrl') ? $school->logoUrl() : null,
            'badge' => null,
        ];
    }

    /**
     * Active schools of the user for the switcher (needs more than one to be shown).
     *
     * @return list<array{id: int|string, name: string, sub: ?string, current: bool}>
     */
    protected function schoolMemberships(?object $user, ?object $school): array
    {
        if ($user === null || ! method_exists($user, 'activeMemberships')) {
            return [];
        }

        $currentId = $school?->getKey();
        $options = [];

        foreach ($user->activeMemberships()->with('school')->get() as $membership) {
            $member = $membership->school;

            if ($member === null) {
                continue;
            }

            $options[] = [
                'id' => $member->getKey(),
                'name' => (string) data_get($member, 'name'),
                'sub' => data_get($member, 'code'),
                'current' => $currentId !== null && $member->getKey() === $currentId,
            ];
        }

        return $options;
    }
}
