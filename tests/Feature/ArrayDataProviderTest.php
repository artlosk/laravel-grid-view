<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Artlosk\LaravelGridView\DataProviders\ArrayDataProvider;
use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Tests\TestCase;
use Closure;
use Illuminate\Http\Request;

class ArrayDataProviderTest extends TestCase
{
    /** @var list<array{name: string, total: array{amount: string}}> */
    protected array $rows = [
        ['name' => 'Charlie', 'total' => ['amount' => '20.50']],
        ['name' => 'Alice', 'total' => ['amount' => '100']],
        ['name' => 'Bob', 'total' => ['amount' => '3']],
        ['name' => 'Dave', 'total' => ['amount' => '20.50']],
    ];

    public function test_sorts_by_a_key_or_a_dotted_path(): void
    {
        $this->assertSame(['Alice', 'Bob', 'Charlie', 'Dave'], $this->names($this->provider('?sort_by=name')));
        $this->assertSame(
            ['Alice', 'Charlie', 'Dave', 'Bob'],
            $this->names($this->provider('?sort_by=amount&sort_direction=desc')),
            'numeric strings compare as numbers, equal values keep their order',
        );
    }

    public function test_default_and_custom_sort(): void
    {
        $this->assertSame(['Bob', 'Charlie', 'Dave', 'Alice'], $this->names($this->provider('', defaultSort: 'amount')));
        $this->assertSame(
            ['Bob', 'Dave', 'Alice', 'Charlie'],
            $this->names($this->provider('?sort_by=length&sort_direction=asc', sort: ['length' => fn (array $row) => strlen($row['name']).$row['name']])),
        );
    }

    public function test_without_sort_keeps_the_given_order(): void
    {
        $this->assertSame(['Charlie', 'Alice', 'Bob', 'Dave'], $this->names($this->provider('?sort_by=unknown', sort: [])));
    }

    public function test_pages(): void
    {
        $paginator = $this->provider('?sort_by=name&page=2', pageSize: 3)->paginator();

        $this->assertSame(['Dave'], array_column($paginator->items(), 'name'));
        $this->assertSame(4, $paginator->total());
        $this->assertSame('http://localhost/rows?sort_by=name&page=1', $paginator->url(1));
        $this->assertSame(4, $this->provider('', pageSize: false)->paginator()->count());
        $this->assertSame(2, $this->provider('?per_page=2', pageSize: 3, maxPageSize: 10)->paginator()->perPage());
    }

    public function test_models_closure_receives_normalized_filters(): void
    {
        $provider = $this->provider(
            '?search=%20a%20',
            filters: [Filter::text('search')],
            models: fn (FilterValues $filters) => array_filter(
                $this->rows,
                fn (array $row) => str_contains(strtolower($row['name']), (string) $filters->string('search')),
            ),
        );

        $this->assertSame(['Charlie', 'Alice', 'Dave'], $this->names($provider));
    }

    public function test_grid_renders_array_rows(): void
    {
        $html = GridView::widget([
            'dataProvider' => $this->provider('?sort_by=amount'),
            'columns' => [
                'name',
                ['key' => 'amount', 'attribute' => 'total.amount', 'label' => 'Amount'],
            ],
            'rowClass' => fn (array $row) => 'row-'.strtolower($row['name']),
        ])->toHtml();

        $this->assertStringContainsString('<tbody class="row-bob">', $html);
        $this->assertStringContainsString('>100<', $html);
        $this->assertStringContainsString('aria-sort="ascending"', $html);
    }

    /**
     * @param  array<array-key, mixed>|null  $sort
     * @param  list<Filter>  $filters
     */
    protected function provider(
        string $url,
        ?array $sort = null,
        ?string $defaultSort = null,
        int|false|null $pageSize = null,
        ?int $maxPageSize = null,
        array $filters = [],
        ?Closure $models = null,
    ): ArrayDataProvider {
        return new ArrayDataProvider(
            models: $models ?? $this->rows,
            sort: $sort ?? ['name', 'amount' => 'total.amount'],
            defaultSort: $defaultSort,
            pageSize: $pageSize,
            maxPageSize: $maxPageSize,
            filters: $filters,
            request: Request::create('/rows'.$url),
        );
    }

    /** @return list<string> */
    protected function names(ArrayDataProvider $provider): array
    {
        return $provider->sortedModels()->pluck('name')->all();
    }
}
