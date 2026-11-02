<?php

namespace App\View\Components\Layouts;

use App\View\Components\Layouts\Concerns\ResolvesShellData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Parent portal layout (mobile first): compact top bar with a child switcher, bottom tab bar on phones.
 *
 * <x-layouts.portal :title="'Attendance'" :children="[['id' => 1, 'name' => 'Aarav Sharma', 'class' => '5-A', 'active' => true]]">
 */
class Portal extends Component
{
    use ResolvesShellData;

    /** The bottom bar holds at most this many tabs; anything beyond goes into a "More" sheet. */
    private const MAX_TABS = 5;

    /** @var list<array<string, mixed>> */
    public array $tabs;

    /** @var list<array<string, mixed>> */
    public array $overflow;

    public bool $overflowActive;

    /** @var list<array{id: int|string, name: string, class: ?string, active: bool}> */
    public array $childList;

    /** @var array{id: int|string, name: string, class: ?string, active: bool}|null */
    public ?array $activeChild;

    /** @var array{name: string, sub: ?string, logo: ?string, badge: ?string} */
    public array $brand;

    /** @var list<array{label: string, items: list<array<string, mixed>>}> */
    public array $menu;

    public ?Authenticatable $user;

    public ?string $switchUrl;

    public ?string $profileUrl;

    public ?string $securityUrl;

    public ?string $logoutUrl;

    /**
     * @param  list<array{id: int|string, name: string, class?: ?string, active?: bool}>  $children
     * @param  list<array{label: string, items: list<array<string, mixed>>}>|null  $menu
     */
    public function __construct(public string $title, array $children = [], ?array $menu = null, ?Authenticatable $user = null)
    {
        $this->user = $user ?? $this->currentUser();
        $this->brand = $this->schoolBrand($this->currentSchool());
        $this->menu = $menu ?? $this->menuFor('portal');

        $this->childList = array_values(array_map(fn (array $child): array => [
            'id' => $child['id'] ?? '',
            'name' => (string) ($child['name'] ?? ''),
            'class' => $child['class'] ?? null,
            'active' => (bool) ($child['active'] ?? false),
        ], $children));
        $this->activeChild = collect($this->childList)->firstWhere('active', true) ?? ($this->childList[0] ?? null);

        $flat = [];
        foreach ($this->menu as $group) {
            foreach ($group['items'] as $item) {
                $flat[] = $item;
            }
        }

        if (count($flat) > self::MAX_TABS) {
            $this->tabs = array_slice($flat, 0, self::MAX_TABS - 1);
            $this->overflow = array_slice($flat, self::MAX_TABS - 1);
        } else {
            $this->tabs = $flat;
            $this->overflow = [];
        }
        $this->overflowActive = collect($this->overflow)->contains(fn (array $item): bool => (bool) ($item['active'] ?? false));

        $this->switchUrl = $this->routeUrl('parent.children.switch');
        $this->profileUrl = $this->routeUrl('account.profile');
        $this->securityUrl = $this->routeUrl('account.security');
        $this->logoutUrl = $this->routeUrl('logout');
    }

    public function render(): View
    {
        return view('components.layouts.portal');
    }
}
