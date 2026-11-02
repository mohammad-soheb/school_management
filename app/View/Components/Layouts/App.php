<?php

namespace App\View\Components\Layouts;

use App\Support\Tenancy\TenantContext;
use App\View\Components\Layouts\Concerns\ResolvesShellData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * School area layout: <x-layouts.app :title="'Students'"> ... </x-layouts.app>
 *
 * Gathers everything itself (user, school in context, academic year, memberships for the switcher, the
 * sidebar menu) and tolerates all of it being absent. `menu` and `user` can be passed to override the
 * defaults (the UI gallery and tests do).
 */
class App extends Component
{
    use ResolvesShellData;

    /** @var list<array{label: string, items: list<array<string, mixed>>}> */
    public array $menu;

    /** @var array{name: string, sub: ?string, logo: ?string, badge: ?string} */
    public array $brand;

    /** @var list<array{id: int|string, name: string, sub: ?string, current: bool}> */
    public array $memberships;

    public ?Authenticatable $user;

    public ?string $academicYear;

    public ?string $switchUrl;

    public ?string $selectUrl;

    public ?string $searchUrl;

    public ?string $profileUrl;

    public ?string $securityUrl;

    public ?string $logoutUrl;

    /**
     * @param  list<array{label: string, items: list<array<string, mixed>>}>|null  $menu
     */
    public function __construct(public string $title, ?array $menu = null, ?Authenticatable $user = null)
    {
        $this->user = $user ?? $this->currentUser();
        $school = $this->currentSchool();

        $this->menu = $menu ?? $this->menuFor('school');
        $this->brand = $this->schoolBrand($school);
        $this->memberships = $this->schoolMemberships($this->user, $school);
        $this->academicYear = $school !== null ? app(TenantContext::class)->academicYearLabel() : null;

        $this->switchUrl = $this->routeUrl('school.switch');
        $this->selectUrl = $this->routeUrl('school.select');
        $this->searchUrl = $this->routeUrl('school.students.index');
        $this->profileUrl = $this->routeUrl('account.profile');
        $this->securityUrl = $this->routeUrl('account.security');
        $this->logoutUrl = $this->routeUrl('logout');
    }

    public function render(): View
    {
        return view('components.layouts.app');
    }
}
