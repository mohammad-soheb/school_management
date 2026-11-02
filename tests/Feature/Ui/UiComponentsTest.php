<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\RendersComponents;
use Tests\TestCase;

/**
 * Renders the <x-ui.*> components with sample data (no database rows needed) and checks the markup contract
 * other modules rely on: classes, ARIA attributes, defaults and fallbacks.
 */
class UiComponentsTest extends TestCase
{
    use RendersComponents;

    public function test_button_renders_variants_sizes_links_and_icons(): void
    {
        $this->blade('<x-ui.button variant="danger" size="sm" icon="trash" type="submit" name="go" value="1">Remove</x-ui.button>')
            ->assertSee('type="submit"', false)
            ->assertSee('class="btn btn-danger btn-sm"', false)
            ->assertSee('name="go"', false)
            ->assertSee('<svg', false)
            ->assertSee('Remove');

        $this->blade('<x-ui.button href="/students" variant="nope">Back</x-ui.button>')
            ->assertSee('<a href="/students"', false)
            ->assertSee('btn-primary', false);

        $this->blade('<x-ui.button block>Wide</x-ui.button>')->assertSee('btn-block', false);
    }

    public function test_badge_and_alert_tones_fall_back_safely(): void
    {
        $this->blade('<x-ui.badge tone="success" dot>Paid</x-ui.badge>')
            ->assertSee('badge badge-success badge-dot', false)->assertSee('Paid');
        $this->blade('<x-ui.badge tone="purple">Odd</x-ui.badge>')->assertSee('badge-neutral', false);

        $this->blade('<x-ui.alert tone="danger" title="Import failed" dismissible>12 rows had errors.</x-ui.alert>')
            ->assertSee('alert-danger', false)
            ->assertSee('role="alert"', false)
            ->assertSee('Import failed')
            ->assertSee('aria-label="Dismiss"', false);
        $this->blade('<x-ui.alert>News</x-ui.alert>')->assertSee('alert-info', false)->assertSee('role="status"', false);
    }

    public function test_card_renders_header_actions_body_and_footer(): void
    {
        $html = '<x-ui.card title="Student details" description="Admission data" :padding="false">'
            .'<x-slot:actions><button>Edit</button></x-slot:actions> Body text <x-slot:footer>Foot</x-slot:footer></x-ui.card>';

        $this->blade($html)
            ->assertSee('card card-flush', false)
            ->assertSee('<h2 class="card-title">Student details</h2>', false)
            ->assertSee('Admission data')
            ->assertSee('card-actions', false)
            ->assertSee('Body text')
            ->assertSee('<footer class="card-footer">Foot</footer>', false);
    }

    public function test_page_header_breadcrumbs_mark_the_current_page(): void
    {
        $this->blade('<x-ui.page-header title="Aarav" description="Class 5-A" :breadcrumbs="[[\'label\' => \'Students\', \'url\' => \'/s\'], [\'label\' => \'Aarav\']]"><x-slot:actions><a href="/e">Edit</a></x-slot:actions></x-ui.page-header>')
            ->assertSee('<h1 class="page-title">Aarav</h1>', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('<a href="/s">Students</a>', false)
            ->assertSee('<span aria-current="page">Aarav</span>', false)
            ->assertSee('page-actions', false);
    }

    public function test_stat_delta_direction(): void
    {
        $this->blade('<x-ui.stat label="Students" value="1,248" icon="academic-cap" delta="+4.2%" hint="vs last year" />')
            ->assertSee('stat-delta is-up', false)->assertSee('1,248')->assertSee('vs last year');
        $this->blade('<x-ui.stat label="Overdue" value="3" delta="-3%" />')->assertSee('is-down', false);
        $this->blade('<x-ui.stat label="Flat" value="3" delta="0%" />')->assertSee('is-flat', false);
        $this->blade('<x-ui.stat label="Linked" value="3" href="/fees" />')->assertSee('<a class="stat"', false)->assertSee('href="/fees"', false);
    }

    public function test_table_and_sortable_headers(): void
    {
        $this->app['request']->query->replace(['sort' => 'name', 'dir' => 'asc', 'status' => 'active', 'page' => '3']);

        $html = '<x-ui.table caption="Students" dense tall><x-slot:head><x-ui.th column="name" label="Student" /><x-ui.th column="class" /><x-ui.th>Plain</x-ui.th></x-slot:head><tr><td>Row</td></tr><x-slot:foot><tr><td>Total</td></tr></x-slot:foot></x-ui.table>';
        $view = $this->blade($html);

        $view->assertSee('table-wrap table-wrap-tall', false)
            ->assertSee('data-table data-table-dense', false)
            ->assertSee('<caption class="sr-only">Students</caption>', false)
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('aria-sort="none"', false)
            ->assertSee('<tfoot>', false)
            ->assertSee('Plain');

        // The active column links to the opposite direction, keeps the filters and drops the page.
        $this->assertMatchesRegularExpression('/href="[^"]*dir=desc[^"]*"/', (string) $view);
        $this->assertStringContainsString('status=active', (string) $view);
        $this->assertStringNotContainsString('page=3', (string) $view);
    }

    public function test_empty_state_and_skeleton(): void
    {
        $this->blade('<x-ui.empty icon="user-group" title="No students yet" description="Admit one."><a href="/new">Admit</a></x-ui.empty>')
            ->assertSee('No students yet')->assertSee('Admit one.')->assertSee('empty-actions', false);
        $this->blade('<x-ui.empty />')->assertSee('Nothing here yet');

        $this->blade('<x-ui.skeleton lines="3" />')->assertSee('is-short', false)->assertSee('Loading');
        $this->blade('<x-ui.skeleton variant="avatar" />')->assertSee('skeleton-avatar', false);
    }

    public function test_modal_and_confirm(): void
    {
        $this->blade('<x-ui.modal name="invite-user" title="Invite" description="By email" max-width="lg">Body<x-slot:footer><button>Send</button></x-slot:footer></x-ui.modal>')
            ->assertSee('x-teleport="body"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('aria-labelledby="modal-title-invite-user"', false)
            ->assertSee('modal-lg', false)
            ->assertSee('modal-footer', false)
            ->assertSee('aria-label="Close dialog"', false);

        $this->withRequestSession();
        $this->blade('<x-ui.confirm action="/students/1" title="Remove?" message="History is kept." confirm="Remove"><button>Trigger</button></x-ui.confirm>')
            ->assertSee('action="/students/1"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('Remove?')
            ->assertSee('History is kept.')
            ->assertSee('btn btn-danger btn-md', false)
            ->assertSee('Trigger');

        $this->blade('<x-ui.confirm action="/publish" method="POST" tone="brand"><button>Go</button></x-ui.confirm>')
            ->assertDontSee('name="_method"', false)
            ->assertSee('btn btn-primary btn-md', false);
    }

    public function test_dropdown_tabs_and_avatar(): void
    {
        $this->blade('<x-ui.dropdown label="Actions"><x-slot:trigger><button>Open</button></x-slot:trigger><x-ui.dropdown-link href="/edit" icon="pencil-square">Edit</x-ui.dropdown-link><x-ui.dropdown-link as="button" danger>Delete</x-ui.dropdown-link></x-ui.dropdown>')
            ->assertSee('role="menu"', false)
            ->assertSee('aria-label="Actions"', false)
            ->assertSee('<a href="/edit" role="menuitem"', false)
            ->assertSee('dropdown-item dropdown-item-danger', false)
            ->assertSee('type="submit"', false);

        $this->blade('<x-ui.tabs pill :tabs="[[\'label\' => \'Overview\', \'url\' => \'/o\', \'active\' => true], [\'label\' => \'Fees\', \'url\' => \'/f\', \'badge\' => 3]]" />')
            ->assertSee('tabs tabs-pill', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('<span class="tab-badge">3</span>', false);

        $this->blade('<x-ui.avatar name="Aarav Kumar Sharma" size="lg" />')->assertSee('AS')->assertSee('avatar-lg', false)->assertSee('aria-label="Aarav Kumar Sharma"', false);
        $this->blade('<x-ui.avatar name="" />')->assertSee('?');
    }

    public function test_progress_chart_dl_copy_and_icon(): void
    {
        $this->blade('<x-ui.progress :value="38" :max="52" tone="success" label="Marks entered" show-value />')
            ->assertSee('progress progress-success', false)->assertSee('73%')->assertSee('aria-label="Marks entered"', false);
        $this->blade('<x-ui.progress :value="250" />')->assertSee('100%');

        $this->blade('<x-ui.chart type="line" label="Attendance" :labels="[\'Mon\', \'Tue\']" :datasets="[[\'label\' => \'Present\', \'data\' => [93, 95]]]" />')
            ->assertSee('x-data="chart(', false)
            ->assertSee('<caption>Attendance</caption>', false)
            ->assertSee('<th scope="row">Tue</th>', false)
            ->assertSee('<td>95</td>', false);

        $this->blade('<x-ui.dl grid><x-ui.dl-item label="Class">5-A</x-ui.dl-item><x-ui.dl-item label="Blood group"></x-ui.dl-item></x-ui.dl>')
            ->assertSee('dl dl-grid', false)->assertSee('5-A')->assertSee('aria-label="Not provided"', false);

        $this->blade('<x-ui.copy text="ADM-1042" label="Copy number" />')
            ->assertSee('x-data="copy(', false)->assertSee('ADM-1042')->assertSee('Copy number');

        // An unknown icon name falls back instead of throwing.
        $this->blade('<x-ui.icon name="definitely-not-an-icon" class="h-5 w-5" />')->assertSee('<svg', false)->assertSee('aria-hidden="true"', false);
    }

    public function test_filters_keep_sort_and_render_search(): void
    {
        $this->app['request']->query->replace(['q' => 'aar', 'sort' => 'name', 'dir' => 'desc']);

        $this->blade('<x-ui.filters reset="/students" placeholder="Search name"><x-form.select name="status" label="Status" :options="[\'active\' => \'Active\']" placeholder="All" /></x-ui.filters>')
            ->assertSee('method="GET"', false)
            ->assertSee('role="search"', false)
            ->assertSee('data-lock="false"', false)
            ->assertSee('name="q" value="aar"', false)
            ->assertSee('<input type="hidden" name="sort" value="name">', false)
            ->assertSee('<input type="hidden" name="dir" value="desc">', false)
            ->assertSee('href="/students"', false);

        $this->blade('<x-ui.filters :search="false"></x-ui.filters>')->assertDontSee('name="q"', false);
    }

    public function test_toasts_show_every_flash_key_and_the_validation_toast(): void
    {
        $this->withFailedSubmit(['email' => 'x'], ['email' => 'Enter a valid email.']);
        $session = $this->app['session.store'];
        $session->put('status', 'password-updated');
        $session->put('success', 'Receipt saved.');
        $session->put('error', 'Payment failed.');
        $session->put('warning', 'Three overdue.');
        $session->put('info', 'Results on Friday.');

        $html = (string) $this->blade('<x-ui.toasts />');

        $this->assertStringContainsString('Password updated.', $html);
        foreach (['Receipt saved.', 'Payment failed.', 'Three overdue.', 'Results on Friday.', 'Please fix the highlighted fields'] as $message) {
            $this->assertStringContainsString($message, $html);
        }
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('<noscript>', $html);

        $this->assertStringNotContainsString('Password updated.', (string) $this->blade('<x-ui.toasts :except="[\'status\']" />'));
    }
}
