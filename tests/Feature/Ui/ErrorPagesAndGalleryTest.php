<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\RendersComponents;
use Tests\TestCase;

/**
 * Error pages render standalone (no session, auth or school), the auth screens render, and the local-only
 * UI gallery (/_ui) and its previews return 200 when the dev routes are loaded.
 */
class ErrorPagesAndGalleryTest extends TestCase
{
    use RendersComponents;

    public function test_every_error_page_renders_standalone(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $html = view("errors.{$code}")->render();

            $this->assertStringContainsString('<!DOCTYPE html>', $html, "errors.{$code}");
            $this->assertStringContainsString('error-code', $html, "errors.{$code}");
            $this->assertStringContainsString((string) $code, $html, "errors.{$code}");
        }
    }

    public function test_an_unknown_url_uses_the_custom_404_page(): void
    {
        $this->get('/definitely-not-a-page-'.uniqid())
            ->assertNotFound()
            ->assertSee('We could not find that page')
            ->assertSee('error-code', false);
    }

    public function test_the_ui_gallery_and_previews_render(): void
    {
        $this->loadDevRoutes();

        $this->get('/_ui')->assertOk()
            ->assertSee('UI gallery')
            ->assertSee('Fee balances')
            ->assertSee('Sample: students')
            ->assertSee('class="pager"', false);

        $this->get('/_ui?invalid=1')->assertOk()
            ->assertSee('Enter a valid email address.')
            ->assertSee('Please fix the highlighted fields');

        $this->get('/_ui/portal')->assertOk()->assertSee('Parent portal');
        $this->get('/_ui/print')->assertOk()->assertSee('paper-a5', false);
        $this->get('/_ui/lookup?q=aar')->assertOk()->assertJsonFragment(['label' => 'Aarav Sharma'])->assertJsonMissing(['label' => 'Kabir Khan']);
    }

    public function test_auth_screens_render_with_the_guest_layout(): void
    {
        $this->loadDevRoutes();

        foreach (['/_ui/auth/login', '/_ui/auth/forgot-password', '/_ui/auth/two-factor', '/_ui/auth/reset-password?email=a@example.test'] as $url) {
            $this->get($url)->assertOk()->assertSee('auth-card', false);
        }
    }
}
