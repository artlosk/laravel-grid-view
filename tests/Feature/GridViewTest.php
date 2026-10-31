<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Artlosk\LaravelGridView\DataProviders\EloquentDataProvider;
use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Formatter;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Rows\ExpandedGroup;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Artlosk\LaravelGridView\Tests\Fixtures\GridRecord;
use Artlosk\LaravelGridView\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

class GridViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        GridRecord::query()->create(['name' => 'Alice <b>', 'rank' => 2]);
        GridRecord::query()->create(['name' => 'Bob', 'rank' => 1]);
    }

    public function test_short_columns_render_labels_and_escaped_values(): void
    {
        $html = $this->grid(['columns' => ['id', 'name:text:Full name', 'rank:number']]);

        $this->assertMatchesRegularExpression('/>\s*Id\s*</', $html);
        $this->assertMatchesRegularExpression('/>\s*Full name\s*</', $html);
        $this->assertStringContainsString('Alice &lt;b&gt;', $html);
        $this->assertStringNotContainsString('Alice <b>', $html);
    }

    public function test_headers_link_only_for_attributes_of_the_data_provider(): void
    {
        $html = $this->grid(['columns' => ['name', 'rank']], sort: ['name'], url: '/records?sort_by=name');

        $this->assertStringContainsString('href="http://localhost/records?sort_by=name&amp;sort_direction=desc"', $html);
        $this->assertStringContainsString('aria-sort="ascending"', $html);
        $this->assertStringNotContainsString('sort_by=rank', $html);
    }

    public function test_sort_link_resets_the_page_and_keeps_filters(): void
    {
        $html = $this->grid(['columns' => ['name']], sort: ['name'], url: '/records?search=a&page=3');

        $this->assertStringContainsString('href="http://localhost/records?search=a&amp;sort_by=name&amp;sort_direction=asc"', $html);
    }

    public function test_value_view_url_and_custom_format(): void
    {
        Formatter::extend('upper', fn ($value) => strtoupper($value));
        config(['grid-view.link_attributes' => ['data-dialog' => 'view']]);

        $html = $this->grid(['columns' => [
            ['key' => 'double', 'label' => 'Double', 'value' => fn (GridRecord $record) => $record->rank * 2],
            ['attribute' => 'rank', 'view' => 'grid-test::status'],
            ['attribute' => 'name', 'format' => 'upper', 'url' => fn (GridRecord $record) => "/records/{$record->id}", 'linkOptions' => ['class' => 'name']],
        ]]);

        $this->assertStringContainsString('>4<', $html);
        $this->assertStringContainsString('<span class="status">2:Alice &lt;b&gt;</span>', $html);
        $this->assertStringContainsString('<a data-dialog="view" class="name" href="/records/1">ALICE &lt;B&gt;</a>', $html);
    }

    public function test_built_in_formats(): void
    {
        $model = new GridRecord;

        $this->assertSame('Yes', Formatter::format(1, 'boolean', $model)->toHtml());
        $this->assertSame('31.10.2026', Formatter::format('2026-10-31 12:00:00', ['date', 'd.m.Y'], $model)->toHtml());
        $this->assertSame('1 234.50', Formatter::format(1234.5, ['number', 2], $model)->toHtml());
        $this->assertSame('<b>', Formatter::format('<b>', 'raw', $model)->toHtml());
        $this->assertSame("a<br />\n", Formatter::format("a\n", 'ntext', $model)->toHtml());
        $this->assertSame('javascript:alert(1)', Formatter::format('javascript:alert(1)', 'url', $model)->toHtml());
        $this->assertStringContainsString('href="https://example.test"', Formatter::format('https://example.test', 'url', $model)->toHtml());
        $this->assertSame('', Formatter::format(null, 'date', $model)->toHtml());
        $this->assertInstanceOf(HtmlString::class, Formatter::format('x', 'text', $model));
    }

    public function test_labels_and_empty_text_go_through_the_translator(): void
    {
        GridView::translateLabelsUsing(fn (string $label) => "t:{$label}");

        $html = $this->grid([
            'columns' => ['name:text:Name'],
            'emptyText' => 'Nothing',
        ], query: GridRecord::query()->whereRaw('1 = 0'));

        $this->assertStringContainsString('t:Name', $html);
        $this->assertStringContainsString('t:Nothing', $html);
    }

    public function test_filters_form_keeps_values_sort_and_shows_reset(): void
    {
        $html = $this->grid(['id' => 'records'], url: '/records?search=Bob&status=2&sort_by=name&sort_direction=desc', sort: ['name'], filters: [
            Filter::text('search', 'Search'),
            Filter::select('status', 'Status', ['1' => 'Active', '2' => 'Inactive']),
        ]);

        $this->assertStringContainsString('data-grid-view-filter="records"', $html);
        $this->assertStringContainsString('name="search"', $html);
        $this->assertStringContainsString('value="Bob"', $html);
        $this->assertMatchesRegularExpression('/value="2"\s+selected/', $html);
        $this->assertStringContainsString('<input type="hidden" name="sort_by" value="name">', $html);
        $this->assertStringContainsString('href="http://localhost/records?sort_by=name&amp;sort_direction=desc"', $html);
        $this->assertStringContainsString('Reset', $html);
    }

    public function test_filters_can_be_hidden_and_reset_needs_a_value(): void
    {
        $filters = [Filter::text('search')];

        $this->assertStringNotContainsString('data-grid-view-filter', $this->grid(['showFilters' => false], filters: $filters));
        $this->assertStringNotContainsString('Reset', $this->grid([], filters: $filters));
    }

    public function test_hidden_filter_keeps_its_value_without_a_field(): void
    {
        $html = $this->grid(['id' => 'records'], url: '/records?country=5', filters: [
            Filter::text('search', 'Search'),
            Filter::select('country', 'Country', ['5' => 'Kyrgyzstan'])->hidden(),
            Filter::select('tags', 'Tags')->multiple()->hidden(),
        ]);

        $this->assertStringContainsString('<input type="hidden" name="country" value="5">', $html);
        $this->assertStringNotContainsString('Country', $html);
        $this->assertStringNotContainsString('tags', $html);
        $this->assertStringContainsString('Reset', $html);
    }

    public function test_select_filter_renders_option_groups(): void
    {
        $html = $this->grid([], url: '/records?source=2', filters: [
            Filter::select('source', 'Source', ['Bank' => ['2' => 'Cash']]),
        ]);

        $this->assertStringContainsString('<optgroup label="Bank">', $html);
        $this->assertMatchesRegularExpression('/value="2"\s+selected/', $html);
    }

    public function test_caption_is_translated_or_printed_as_html(): void
    {
        GridView::translateLabelsUsing(fn (string $label) => "t:{$label}");

        $this->assertStringContainsString('<div class="lgv-caption">t:Records &amp; more</div>', $this->grid(['caption' => 'Records & more']));
        $this->assertStringContainsString('<div class="lgv-caption"><b>Total</b></div>', $this->grid(['caption' => new HtmlString('<b>Total</b>')]));
        $this->assertStringNotContainsString('lgv-caption', $this->grid([]));
    }

    public function test_reorder_adds_a_handle_and_record_keys(): void
    {
        $html = $this->reorderGrid('/records', ['url' => '/records/order', 'method' => 'put', 'sort' => 'rank']);

        $this->assertStringContainsString('data-grid-view-reorder-url="/records/order"', $html);
        $this->assertStringContainsString('data-grid-view-reorder-method="PUT"', $html);
        $this->assertMatchesRegularExpression('/<tbody data-grid-view-key="2">.*<tbody data-grid-view-key="1">/s', $html);
        $this->assertSame(2, substr_count($html, 'data-grid-view-handle'));
        $this->assertStringContainsString('Drag or press', $html);
    }

    public function test_reorder_needs_its_order_and_a_single_page(): void
    {
        $options = ['url' => '/records/order', 'sort' => 'rank'];

        $this->assertStringNotContainsString('data-grid-view-handle', $this->reorderGrid('/records?sort_by=name', $options));
        $this->assertStringNotContainsString('data-grid-view-handle', $this->reorderGrid('/records?sort_by=rank&sort_direction=desc', $options));
        $this->assertStringNotContainsString('data-grid-view-key', $this->reorderGrid('/records', $options, pageSize: 1));
    }

    public function test_reorder_options_fail_fast(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->reorderGrid('/records', ['url' => '/records/order', 'column' => 'rank']);
    }

    public function test_expanded_rows_share_cells(): void
    {
        $html = $this->grid([
            'columns' => ['id', 'name'],
            'rowExpander' => fn (GridRecord $record) => ExpandedGroup::make([
                ExpandedRow::make(),
                ExpandedRow::make(['name' => 'detail of '.$record->id], 'detail'),
            ], ['id'], 'group'),
            'rowClass' => fn (GridRecord $record) => 'rank-'.$record->rank,
        ]);

        $this->assertStringContainsString('<tbody class="rank-2 group">', $html);
        $this->assertStringContainsString('rowspan="2"', $html);
        $this->assertStringContainsString('detail of 1', $html);
    }

    public function test_serial_column_counts_across_pages(): void
    {
        $html = $this->grid(['columns' => [['class' => 'serial'], 'name']], url: '/records?page=2', pageSize: 1);

        $this->assertMatchesRegularExpression('/<td\s*>2<\/td>/', $html);
    }

    public function test_grid_directive(): void
    {
        $provider = new EloquentDataProvider(GridRecord::query());

        $html = Blade::render("@grid(['id' => 'directive', 'dataProvider' => \$provider, 'columns' => ['name']])", ['provider' => $provider]);

        $this->assertStringContainsString('id="directive"', $html);
        $this->assertStringContainsString('Bob', $html);
    }

    public function test_unknown_options_fail_fast(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->grid(['columns' => [['attribute' => 'name', 'lable' => 'Typo']]]);
    }

    public function test_unknown_grid_option_fails_fast(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->grid(['filterModel' => null]);
    }

    /**
     * @param  array<string, mixed>  $reorder
     */
    protected function reorderGrid(string $url, array $reorder, ?int $pageSize = null): string
    {
        $provider = new EloquentDataProvider(
            query: GridRecord::query(),
            sort: ['rank', 'name'],
            defaultSort: 'rank',
            pageSize: $pageSize,
            request: Request::create($url),
        );

        return GridView::widget(['dataProvider' => $provider, 'columns' => ['name'], 'reorder' => $reorder])->toHtml();
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<array-key, mixed>  $sort
     * @param  list<Filter>  $filters
     */
    protected function grid(
        array $config,
        string $url = '/records',
        array $sort = [],
        array $filters = [],
        int|false|null $pageSize = null,
        mixed $query = null,
    ): string {
        $provider = new EloquentDataProvider(
            query: $query ?? GridRecord::query(),
            sort: $sort,
            pageSize: $pageSize,
            filters: $filters,
            request: Request::create($url),
        );

        return GridView::widget(['dataProvider' => $provider, 'columns' => ['name'], ...$config])->toHtml();
    }
}
