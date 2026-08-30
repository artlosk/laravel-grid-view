<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Artlosk\LaravelGridView\Columns\Column;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Rows\ExpandedGroup;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Artlosk\LaravelGridView\Tests\Fixtures\GridRecord;
use Artlosk\LaravelGridView\Tests\TestCase;

class GridViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        GridRecord::query()->insert([
            ['name' => 'Charlie', 'rank' => 2],
            ['name' => 'Alice', 'rank' => 3],
            ['name' => 'Bob', 'rank' => 1],
        ]);
    }

    public function test_it_sorts_only_by_declared_sortable_columns(): void
    {
        $request = Request::create('/records', 'GET', [
            'sort_by' => 'name',
            'sort_direction' => 'desc',
        ]);

        $grid = GridView::make(GridRecord::query()->orderBy('rank'), $request)
            ->columns([
                Column::make('name')->sortable(),
                Column::make('rank'),
            ]);

        $this->assertSame(['Charlie', 'Bob', 'Alice'], $grid->records()->pluck('name')->all());

        $unlisted = GridView::make(
            GridRecord::query()->orderBy('rank'),
            Request::create('/records', 'GET', ['sort_by' => 'rank']),
        )->columns([
            Column::make('name')->sortable(),
            Column::make('rank'),
        ]);

        $this->assertSame(['Bob', 'Charlie', 'Alice'], $unlisted->records()->pluck('name')->all());

        $hidden = GridView::make(
            GridRecord::query()->orderBy('rank'),
            Request::create('/records', 'GET', ['sort_by' => 'name']),
        )->columns([
            Column::make('name')->sortable()->visible(false),
        ]);

        $this->assertSame(['Bob', 'Charlie', 'Alice'], $hidden->records()->pluck('name')->all());
    }

    public function test_it_supports_custom_sort_closures_and_pagination(): void
    {
        $grid = GridView::make(
            GridRecord::query(),
            Request::create('/records', 'GET', ['sort_by' => 'display_name']),
        )
            ->columns([
                Column::make('display_name')->sortable(
                    fn ($query, string $direction) => $query->orderBy('name', $direction),
                ),
            ])
            ->paginate(2);

        $this->assertSame(['Alice', 'Bob'], $grid->records()->pluck('name')->all());
        $this->assertSame(3, $grid->records()->total());

        $secondPage = GridView::make(
            GridRecord::query(),
            Request::create('/records', 'GET', [
                'sort_by' => 'display_name',
                'page' => 2,
            ]),
        )
            ->columns([
                Column::make('display_name')->sortable(
                    fn ($query, string $direction) => $query->orderBy('name', $direction),
                ),
            ])
            ->paginate(2);

        $this->assertSame(['Charlie'], $secondPage->records()->pluck('name')->all());
    }

    public function test_it_supports_mapped_columns_default_sort_and_query_preservation(): void
    {
        $request = Request::create('/records', 'GET', [
            'filter' => 'active',
            'records_sort' => 'display_name',
            'records_direction' => 'invalid',
        ]);

        $grid = GridView::make(GridRecord::query(), $request)
            ->columns([
                Column::make('display_name')->sortable('name'),
            ])
            ->sortParameters('records_sort', 'records_direction')
            ->paginate(2, 'records_page')
            ->defaultSort('display_name', 'desc');

        $this->assertSame('asc', $grid->requestedDirection());
        $this->assertSame(['Alice', 'Bob'], $grid->records()->pluck('name')->all());

        $url = $grid->sortUrl(Column::make('rank')->sortable());
        $this->assertStringContainsString('filter=active', $url);
        $this->assertStringContainsString('records_sort=rank', $url);
        $this->assertStringContainsString('records_direction=asc', $url);
        $this->assertStringContainsString('records_page=1', $url);
    }

    public function test_sorting_uses_a_primary_key_tie_breaker_for_stable_pagination(): void
    {
        GridRecord::query()->create(['name' => 'Dave', 'rank' => 1]);

        $firstPage = GridView::make(
            GridRecord::query(),
            Request::create('/records', 'GET', ['sort_by' => 'rank']),
        )
            ->columns([Column::make('rank')->sortable()])
            ->paginate(1);
        $secondPage = GridView::make(
            GridRecord::query(),
            Request::create('/records', 'GET', ['sort_by' => 'rank', 'page' => 2]),
        )
            ->columns([Column::make('rank')->sortable()])
            ->paginate(1);

        $this->assertSame('Bob', $firstPage->records()->first()->name);
        $this->assertSame('Dave', $secondPage->records()->first()->name);
    }

    public function test_query_configuration_invalidates_cached_records(): void
    {
        $grid = GridView::make(GridRecord::query()->orderBy('id'), Request::create('/records'))
            ->columns([
                Column::make('name')->sortable(),
            ])
            ->paginate(1);

        $this->assertSame('Charlie', $grid->records()->first()->name);

        $grid->defaultSort('name')->paginate(2);

        $this->assertSame(['Alice', 'Bob'], $grid->records()->pluck('name')->all());
        $this->assertSame(2, $grid->records()->perPage());
    }

    public function test_internal_partial_parameters_do_not_leak_into_navigation_urls(): void
    {
        $grid = GridView::make(
            GridRecord::query(),
            Request::create('/records?_grid_partial=1&_grid_id=people-grid&filter=active'),
            'people-grid',
        )
            ->columns([Column::make('name')->sortable()])
            ->paginate(1);

        $sortUrl = $grid->sortUrl(Column::make('name')->sortable());
        $html = $grid->render();

        $this->assertStringContainsString('filter=active', $sortUrl);
        $this->assertStringNotContainsString('_grid_partial', $sortUrl);
        $this->assertStringNotContainsString('_grid_id', $sortUrl);
        $this->assertStringNotContainsString('_grid_partial', $html);
        $this->assertStringNotContainsString('_grid_id', $html);
    }

    public function test_it_renders_visible_columns_and_expanded_rows_with_rowspan(): void
    {
        $grid = GridView::make(
            GridRecord::query()->where('name', 'Alice'),
            Request::create('/records'),
            'people-grid',
        )
            ->columns([
                Column::make('name')->label('Name'),
                Column::make('detail'),
                Column::make('hidden')->visible(false),
                Column::make('html')->render(fn () => new HtmlString('<strong>Safe</strong>')),
            ])
            ->rowExpander(fn () => ExpandedGroup::make(
                [
                    ExpandedRow::make(['detail' => 'First'], 'first-row'),
                    ExpandedRow::make(['detail' => 'Second'], 'second-row'),
                ],
                sharedColumns: ['name', 'html'],
                class: 'expanded-group',
            ))
            ->rowClass('record-group');

        $html = $grid->render();

        $this->assertStringContainsString('id="people-grid"', $html);
        $this->assertStringContainsString('rowspan="2"', $html);
        $this->assertSame(1, substr_count($html, 'Alice'));
        $this->assertStringContainsString('First', $html);
        $this->assertStringContainsString('Second', $html);
        $this->assertStringContainsString('<strong>Safe</strong>', $html);
        $this->assertStringNotContainsString('hidden', $html);
        $this->assertMatchesRegularExpression(
            '/<tbody class="record-group expanded-group">\s*<tr class="first-row">/',
            $html,
        );
        $this->assertStringContainsString('<tr class="second-row">', $html);
        $this->assertStringNotContainsString('<tr class="record-group', $html);
    }

    public function test_each_logical_record_and_empty_state_have_separate_tbody(): void
    {
        $grid = GridView::make(GridRecord::query(), Request::create('/records'))
            ->columns([Column::make('name')]);

        $this->assertSame(3, substr_count($grid->render(), '<tbody'));

        $emptyGrid = GridView::make(
            GridRecord::query()->whereRaw('1 = 0'),
            Request::create('/records'),
        )->columns([Column::make('name')]);

        $emptyHtml = $emptyGrid->render();

        $this->assertSame(1, substr_count($emptyHtml, '<tbody'));
        $this->assertStringContainsString('class="lgv-empty"', $emptyHtml);
    }

    public function test_it_renders_custom_grid_table_wrapper_and_pagination_theme(): void
    {
        View::addNamespace('grid-test', dirname(__DIR__).'/Fixtures/views');

        $grid = GridView::make(
            GridRecord::query()->orderBy('id'),
            Request::create('/records'),
            'custom-grid',
        )
            ->columns([Column::make('name')])
            ->paginate(1)
            ->gridClass('host-grid custom-root')
            ->tableWrapperClass('host-table-responsive')
            ->tableClass('table table-striped')
            ->paginationView('grid-test::pagination')
            ->paginationWrapperClass('host-pagination-wrapper')
            ->ajax();

        $html = $grid->render();

        $this->assertStringContainsString('class="host-grid custom-root"', $html);
        $this->assertStringContainsString('data-grid-view-id="custom-grid"', $html);
        $this->assertStringContainsString('class="host-table-responsive"', $html);
        $this->assertStringContainsString('class="table table-striped"', $html);
        $this->assertStringContainsString('class="host-pagination-wrapper"', $html);
        $this->assertStringContainsString('class="host-pagination-view"', $html);
    }

    public function test_default_theme_classes_and_pagination_view_are_preserved(): void
    {
        $grid = GridView::make(GridRecord::query(), Request::create('/records'))
            ->columns([Column::make('name')])
            ->paginate(1);

        $html = $grid->render();

        $this->assertStringContainsString('class="lgv-grid"', $html);
        $this->assertStringContainsString('class="lgv-table-wrap"', $html);
        $this->assertStringContainsString('class="lgv-table"', $html);
        $this->assertStringContainsString('data-grid-view-pagination', $html);
        $this->assertStringContainsString('class="lgv-pagination"', $html);
    }

    public function test_partial_response_has_configured_header(): void
    {
        $grid = GridView::make(GridRecord::query(), Request::create('/records'))
            ->columns([Column::make('name')]);

        $response = $grid->partialResponse();

        $this->assertSame('1', $response->headers->get('X-Grid-View-Partial'));
        $this->assertSame('grid-view', $response->headers->get('X-Grid-View-ID'));
        $this->assertSame(
            'X-Grid-View-Partial, X-Grid-View-ID, X-Requested-With',
            $response->headers->get('Vary'),
        );
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('class="lgv-grid"', $response->getContent());
    }

    public function test_it_normalizes_invalid_configured_page_size(): void
    {
        config()->set('grid-view.per_page', 0);

        $grid = GridView::make(GridRecord::query(), Request::create('/records'))
            ->columns([Column::make('name')]);

        $this->assertSame(1, $grid->records()->perPage());
    }

    public function test_it_rejects_invalid_or_conflicting_parameter_names(): void
    {
        $grid = GridView::make(GridRecord::query(), Request::create('/records'));

        $this->expectException(InvalidArgumentException::class);

        $grid->sortParameters('sort', 'sort');
    }

    public function test_it_rejects_an_invalid_grid_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GridView::make(GridRecord::query(), Request::create('/records'), 'invalid grid');
    }
}
