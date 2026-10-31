<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Artlosk\LaravelGridView\DataProviders\EloquentDataProvider;
use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;
use Artlosk\LaravelGridView\Tests\Fixtures\GridRecord;
use Artlosk\LaravelGridView\Tests\TestCase;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DataProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['Charlie', 2], ['Alice', 3], ['Bob', 1]] as [$name, $rank]) {
            GridRecord::query()->create(['name' => $name, 'rank' => $rank]);
        }
    }

    public function test_sorts_by_an_allowed_attribute_from_the_request(): void
    {
        $provider = $this->provider('?sort_by=name&sort_direction=desc', sort: ['name', 'rank']);

        $this->assertSame(['Charlie', 'Bob', 'Alice'], $this->names($provider));
        $this->assertSame('name', $provider->sort()->column());
        $this->assertSame('desc', $provider->sort()->direction());
    }

    public function test_ignores_attributes_outside_the_allow_list(): void
    {
        $provider = $this->provider('?sort_by=rank', sort: ['name'], defaultSort: 'name');

        $this->assertSame(['Alice', 'Bob', 'Charlie'], $this->names($provider));
    }

    public function test_default_sort_with_a_minus_is_descending(): void
    {
        $provider = $this->provider('', sort: ['rank'], defaultSort: '-rank');

        $this->assertSame(['Alice', 'Charlie', 'Bob'], $this->names($provider));
    }

    public function test_requested_attribute_without_direction_is_ascending(): void
    {
        $provider = $this->provider('?sort_by=rank', sort: ['rank', 'name'], defaultSort: '-name');

        $this->assertSame(['Bob', 'Charlie', 'Alice'], $this->names($provider));
    }

    public function test_custom_sort_closure(): void
    {
        $provider = $this->provider('?sort_by=score&sort_direction=asc', sort: [
            'score' => fn (Builder $query, string $direction) => $query->orderByRaw('rank * -1 '.$direction),
        ]);

        $this->assertSame(['Alice', 'Charlie', 'Bob'], $this->names($provider));
    }

    public function test_sort_set_by_code_ignores_the_request(): void
    {
        $provider = $this->provider('?sort_by=name', sort: ['name', 'rank'])->sortBy('rank', 'desc');

        $this->assertSame(['Alice', 'Charlie', 'Bob'], $this->names($provider));
    }

    public function test_stable_pagination_orders_by_the_key_last(): void
    {
        $sql = $this->provider('?sort_by=name', sort: ['name'])->sortedQuery()->toSql();

        $this->assertStringEndsWith('order by "name" asc, "grid_records"."id" asc', $sql);
    }

    public function test_page_size_and_client_limit(): void
    {
        $this->assertSame(2, $this->provider('?per_page=50', pageSize: 2)->paginator()->perPage());
        $this->assertSame(3, $this->provider('?per_page=3', pageSize: 2, maxPageSize: 10)->paginator()->perPage());
        $this->assertSame(10, $this->provider('?per_page=999', pageSize: 2, maxPageSize: 10)->paginator()->perPage());
        $this->assertSame(2, $this->provider('?per_page[]=5', pageSize: 2, maxPageSize: 10)->paginator()->perPage());
    }

    public function test_page_size_false_returns_everything(): void
    {
        $paginator = $this->provider('', pageSize: false)->paginator();

        $this->assertSame(3, $paginator->count());
        $this->assertFalse($paginator->hasPages());
    }

    public function test_pagination_links_keep_the_query(): void
    {
        $paginator = $this->provider('?sort_by=name&search=a&page=1', sort: ['name'], pageSize: 1)->paginator();

        $this->assertSame('http://localhost/records?sort_by=name&search=a&page=2', $paginator->url(2));
        $this->assertSame(1, $this->provider('?page=abc', pageSize: 1)->paginator()->currentPage());
    }

    public function test_query_closure_receives_normalized_filters(): void
    {
        $received = null;
        $provider = $this->provider(
            '?search=%20ali%20&rank[]=3&unknown=1',
            filters: [Filter::text('search'), Filter::select('rank', items: ['1' => 'One', '3' => 'Three'])],
            query: function (FilterValues $filters) use (&$received) {
                $received = $filters->all();

                return GridRecord::query()->where('name', 'like', '%'.$filters->string('search').'%');
            },
        );

        $this->assertSame(['Alice'], $this->names($provider));
        $this->assertSame(['search' => 'ali', 'rank' => null], $received);
    }

    /**
     * @param  array<array-key, mixed>  $sort
     * @param  list<Filter>  $filters
     */
    protected function provider(
        string $url,
        array $sort = [],
        ?string $defaultSort = null,
        int|false|null $pageSize = null,
        ?int $maxPageSize = null,
        array $filters = [],
        ?Closure $query = null,
    ): EloquentDataProvider {
        return new EloquentDataProvider(
            query: $query ?? GridRecord::query(),
            sort: $sort,
            defaultSort: $defaultSort,
            pageSize: $pageSize,
            maxPageSize: $maxPageSize,
            filters: $filters,
            request: Request::create('/records'.$url),
        );
    }

    /** @return list<string> */
    protected function names(EloquentDataProvider $provider): array
    {
        return collect($provider->paginator()->items())->pluck('name')->all();
    }
}
