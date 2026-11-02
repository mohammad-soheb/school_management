<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\Feature\Ui\Concerns\RendersComponents;
use Tests\TestCase;

/**
 * Every layout renders for a guest without a school in context (sample menu + demo user), and the shell
 * shows group labels, icons, the active item, the off-canvas drawer controls and the flash toasts.
 */
class LayoutsTest extends TestCase
{
    use RendersComponents;

    private function sampleMenu(): array
    {
        return [
            ['label' => 'People', 'items' => [
                ['label' => 'Students', 'url' => '/app/students', 'icon' => 'academic-cap', 'active' => true, 'badge' => null],
                ['label' => 'Staff', 'url' => '/app/staff', 'icon' => 'identification', 'active' => false, 'badge' => '2'],
            ]],
            ['label' => 'Finance', 'items' => [
                ['label' => 'Fee collection', 'url' => '/app/fees', 'icon' => 'banknotes', 'active' => false, 'badge' => null],
            ]],
        ];
    }

    private function demoUser(): User
    {
        return new User(['name' => 'Priya Nair', 'email' => 'priya.nair@sunrise.example']);
    }

    public function test_app_layout_renders_the_shell_menu_and_flash_toasts(): void
    {
        $this->withRequestSession();
        $this->app['session.store']->put('success', 'Receipt R-1042 saved.');

        $view = $this->blade('<x-layouts.app title="Students" :menu="$menu" :user="$user">Page body</x-layouts.app>', ['menu' => $this->sampleMenu(), 'user' => $this->demoUser()]);

        $view->assertSee('<title>Students · '.config('app.name').'</title>', false)
            ->assertSee('class="app-body"', false)
            ->assertSee('Skip to main content')
            ->assertSee('id="app-sidebar"', false)
            ->assertSee('aria-label="Open navigation"', false)
            ->assertSee('nav-group-label', false)
            ->assertSee('People')
            ->assertSee('Finance')
            ->assertSee('nav-link is-active', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('<span class="nav-badge">2</span>', false)
            ->assertSee('Priya Nair')
            ->assertSee('Toggle dark mode', false)
            ->assertSee('Receipt R-1042 saved.')
            ->assertSee('Page body');
    }

    public function test_app_layout_shows_an_empty_menu_message(): void
    {
        $this->withRequestSession();

        $this->blade('<x-layouts.app title="Empty" :menu="$menu">Body</x-layouts.app>', ['menu' => []])
            ->assertSee('No menu items yet.')
            ->assertSee('Guest');
    }

    public function test_platform_layout_carries_the_platform_badge(): void
    {
        $this->withRequestSession();

        $this->blade('<x-layouts.platform title="Schools" :menu="$menu" :user="$user">Schools body</x-layouts.platform>', ['menu' => $this->sampleMenu(), 'user' => $this->demoUser()])
            ->assertSee('Platform')
            ->assertSee('Fee collection')
            ->assertSee('Schools body');
    }

    public function test_portal_layout_has_tabs_overflow_and_child_switcher(): void
    {
        $this->withRequestSession();

        $items = [];
        foreach (['Home', 'Attendance', 'Fees', 'Homework', 'Results', 'Notices'] as $i => $label) {
            $items[] = ['label' => $label, 'url' => '/parent/'.strtolower($label), 'icon' => 'home', 'active' => $i === 5, 'badge' => null];
        }

        $view = $this->blade('<x-layouts.portal title="Home" :menu="$menu" :user="$user" :children="$children">Portal body</x-layouts.portal>', [
            'menu' => [['label' => 'Portal', 'items' => $items]],
            'user' => $this->demoUser(),
            'children' => [
                ['id' => 11, 'name' => 'Aarav Sharma', 'class' => '5-A', 'active' => true],
                ['id' => 12, 'name' => 'Diya Sharma', 'class' => '2-B'],
            ],
        ]);

        $view->assertSee('class="portal-body"', false)
            ->assertSee('portal-tabbar', false)
            ->assertSee('More')
            ->assertSee('Aarav Sharma')
            ->assertSee('Class 5-A')
            ->assertSee('Notices')
            ->assertSee('Portal body');
    }

    public function test_guest_layout_renders_the_auth_shell(): void
    {
        $this->withRequestSession();

        $this->blade('<x-layouts.guest title="Sign in" heading="Welcome back" subheading="Sign in to your school account">Guest body</x-layouts.guest>')
            ->assertSee('class="auth-body"', false)
            ->assertSee('auth-panel', false)
            ->assertSee('Welcome back')
            ->assertSee('Sign in to your school')
            ->assertSee('Guest body');
    }

    public function test_print_layout_sets_the_paper_and_has_no_toasts_or_dark_mode(): void
    {
        $this->withRequestSession();
        $this->app['session.store']->put('success', 'Should not print');

        foreach (['a4' => 'paper-a4', 'a5' => 'paper-a5', 'a6' => 'paper-a6', 'card' => 'paper-card', 'letter' => 'paper-a4'] as $paper => $class) {
            $this->blade('<x-layouts.print title="Receipt" :paper="$paper" back="/fees">Receipt body</x-layouts.print>', ['paper' => $paper])
                ->assertSee($class, false)
                ->assertSee('theme-light', false)
                ->assertSee('no-print', false)
                ->assertSee('<meta name="color-scheme" content="light">', false)
                ->assertDontSee('Should not print')
                ->assertSee('Receipt body');
        }
    }
}
