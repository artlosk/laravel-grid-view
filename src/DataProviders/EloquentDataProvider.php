<?php

namespace Artlosk\LaravelGridView\DataProviders;

use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Records of an Eloquent query for a grid or an API list.
 *
 *     new EloquentDataProvider(
 *         query: fn (FilterValues $filters) => User::query()->where(...),
 *         filters: [Filter::text('search'), Filter::select('status', items: [...])],
 *         sort: ['id', 'name', 'created_at'],
 *         defaultSort: '-created_at',
 *         pageSize: 50,
 *     );
 */
class EloquentDataProvider extends BaseDataProvider
{
    /**
     * @param  Builder|Closure(FilterValues): Builder  $query  the closure receives normalized filter values
     * @param  array<array-key, string|Closure>  $sort  'name', 'balance' => 'accounts.current_balance' or 'owner' => fn (Builder $query, string $direction) => ...
     * @param  string|null  $defaultSort  'name' or '-created_at'
     * @param  int|false|null  $pageSize  null: config value, false: everything on one page
     * @param  int|null  $maxPageSize  let the client choose the page size (?per_page=) up to this limit
     * @param  list<Filter>  $filters
     * @param  string|Closure(Builder, string): void|null  $tieBreaker  extra ordering for stable pages, primary key by default
     */
    public function __construct(
        protected Builder|Closure $query,
        array $sort = [],
        ?string $defaultSort = null,
        int|false|null $pageSize = null,
        ?int $maxPageSize = null,
        array $filters = [],
        protected string|Closure|null $tieBreaker = null,
        ?Request $request = null,
    ) {
        parent::__construct($sort, $defaultSort, $pageSize, $maxPageSize, $filters, $request);
    }

    // Filtered query without ordering by the grid
    public function query(): Builder
    {
        return clone ($this->query instanceof Closure ? ($this->query)($this->filters()) : $this->query);
    }

    public function sortedQuery(): Builder
    {
        $query = $this->query();
        $sorted = $this->sort->apply($query);

        if (config('grid-view.stable_pagination', true)) {
            $direction = $sorted ? $this->sort->direction() : 'asc';

            if ($this->tieBreaker instanceof Closure) {
                ($this->tieBreaker)($query, $direction);
            } else {
                $query->orderBy($this->tieBreaker ?? $query->getModel()->getQualifiedKeyName(), $direction);
            }
        }

        return $query;
    }

    protected function paginate(string $pageName, int $page): LengthAwarePaginator
    {
        $query = $this->sortedQuery();

        if ($this->pageSize === false) {
            $items = $query->get();

            return new Paginator($items, $items->count(), max(1, $items->count()), 1, ['pageName' => $pageName]);
        }

        return $query->paginate($this->pageSize(), ['*'], $pageName, $page);
    }
}
