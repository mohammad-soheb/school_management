<?php

namespace App\View\Components\Layouts;

use App\View\Components\Layouts\Concerns\ResolvesShellData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Product-owner backoffice layout (same shell as the school area, "Platform" badge instead of a school).
 * <x-layouts.platform :title="'Schools'"> ... </x-layouts.platform>
 */
class Platform extends Component
{
    use ResolvesShellData;

    /** @var list<array{label: string, items: list<array<string, mixed>>}> */
    public array $menu;

    /** @var array{name: string, sub: ?string, logo: ?string, badge: ?string} */
    public array $brand;

    public ?Authenticatable $user;

    public ?string $profileUrl;

    public ?string $securityUrl;

    public ?string $logoutUrl;

    /**
     * @param  list<array{label: string, items: list<array<string, mixed>>}>|null  $menu
     */
    public function __construct(public string $title, ?array $menu = null, ?Authenticatable $user = null)
    {
        $this->user = $user ?? $this->currentUser();
        $this->menu = $menu ?? $this->menuFor('platform');
        $this->brand = ['name' => (string) config('app.name'), 'sub' => null, 'logo' => null, 'badge' => 'Platform'];

        $this->profileUrl = $this->routeUrl('account.profile');
        $this->securityUrl = $this->routeUrl('account.security');
        $this->logoutUrl = $this->routeUrl('logout');
    }

    public function render(): View
    {
        return view('components.layouts.platform');
    }
}
