<?php

namespace Artlosk\LaravelGridView\DataProviders;

use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Records that are already in memory (report rows, results of an external API) for a grid or an API list.
 *
 *     new ArrayDataProvider(
 *         models: $report['rows'],
 *         sort: ['name', 'amount' => 'totals.amount'],
 *         defaultSort: '-amount',
 *         pageSize: 20,
 *     );
 */
class ArrayDataProvider extends BaseDataProvider
{
    /**
     * @param  iterable<array-key, mixed>|Closure(FilterValues): iterable<array-key, mixed>  $models  arrays or objects; the closure receives normalized filter values
     * @param  array<array-key, string|Closure>  $sort  'name', 'total' => 'totals.amount' or 'owner' => fn ($model) => ...
     * @param  string|null  $defaultSort  'name' or '-amount'
     * @param  int|false|null  $pageSize  null: config value, false: everything on one page
     * @param  int|null  $maxPageSize  let the client choose the page size (?per_page=) up to this limit
     * @param  list<Filter>  $filters
     */
    public function __construct(
        protected iterable|Closure $models,
        array $sort = [],
        ?string $defaultSort = null,
        int|false|null $pageSize = null,
        ?int $maxPageSize = null,
        array $filters = [],
        ?Request $request = null,
    ) {
        parent::__construct($sort, $defaultSort, $pageSize, $maxPageSize, $filters, $request);
    }

    /**
     * All filtered records in the current order
     *
     * @return Collection<int, mixed>
     */
    public function sortedModels(): Collection
    {
        $models = $this->models instanceof Closure ? ($this->models)($this->filters()) : $this->models;

        return $this->sort->sortCollection(Collection::make($models));
    }

    protected function paginate(string $pageName, int $page): LengthAwarePaginator
    {
        $models = $this->sortedModels();

        if ($this->pageSize === false) {
            return new Paginator($models, $models->count(), max(1, $models->count()), 1, ['pageName' => $pageName]);
        }

        $size = $this->pageSize();

        return new Paginator($models->forPage($page, $size)->values(), $models->count(), $size, $page, ['pageName' => $pageName]);
    }
}
