<?php

namespace Tests\Feature\Ui;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

/** The paginator views in resources/views/vendor/pagination override Laravel's defaults. */
class PaginationTest extends TestCase
{
    public function test_length_aware_links_show_the_range_numbers_and_ellipsis(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 25), 1248, 25, 10, ['path' => '/app/students']);
        $html = (string) $paginator->links();

        $this->assertStringContainsString('class="pager"', $html);
        $this->assertStringContainsString('Showing <strong>226</strong>&ndash;<strong>250</strong>', $html);
        $this->assertStringContainsString('of <strong>1,248</strong> results', $html);
        $this->assertStringContainsString('aria-current="page"><span class="sr-only">Page </span>10</span>', $html);
        $this->assertStringContainsString('&hellip;', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringContainsString('Page 10 of 50', $html);
    }

    public function test_first_and_last_pages_disable_the_edges(): void
    {
        $first = (string) (new LengthAwarePaginator(range(1, 10), 30, 10, 1, ['path' => '/x']))->links();
        $this->assertStringContainsString('is-disabled', $first);
        $this->assertStringNotContainsString('rel="prev"', $first);

        $last = (string) (new LengthAwarePaginator(range(1, 10), 30, 10, 3, ['path' => '/x']))->links();
        $this->assertStringNotContainsString('rel="next"', $last);
    }

    public function test_a_single_page_renders_nothing(): void
    {
        $this->assertSame('', trim((string) (new LengthAwarePaginator(range(1, 5), 5, 25, 1))->links()));
    }

    public function test_simple_and_cursor_paginators(): void
    {
        $simple = (string) (new Paginator(range(1, 11), 10, 2, ['path' => '/x']))->links();
        $this->assertStringContainsString('Page 2', $simple);
        $this->assertStringContainsString('rel="prev"', $simple);
        $this->assertStringContainsString('rel="next"', $simple);
        $this->assertStringNotContainsString(' of ', $simple);

        $cursor = (string) (new CursorPaginator(
            collect(range(1, 11))->map(fn ($i) => ['id' => $i]),
            10,
            new Cursor(['id' => 10]),
            ['path' => '/x', 'parameters' => ['id']],
        ))->links();
        $this->assertStringContainsString('class="pager"', $cursor);
        $this->assertStringContainsString('Showing <strong>10</strong> results', $cursor);
    }
}
