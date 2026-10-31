<?php

namespace Artlosk\LaravelGridView\DataProviders;

use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;
use Artlosk\LaravelGridView\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Request, filters, sorting and page size shared by data providers; a subclass only fetches a page of records.
 */
abstract class BaseDataProvider implements DataProvider
{
    protected Request $request;

    protected Sort $sort;

    protected ?FilterValues $filterValues = null;

    protected ?LengthAwarePaginator $paginator = null;

    /**
     * @param  array<array-key, string|\Closure>  $sort  attributes allowed for sorting
     * @param  string|null  $defaultSort  'name' or '-created_at'
     * @param  int|false|null  $pageSize  null: config value, false: everything on one page
     * @param  int|null  $maxPageSize  let the client choose the page size (?per_page=) up to this limit
     * @param  list<Filter>  $filters
     */
    public function __construct(
        array $sort = [],
        ?string $defaultSort = null,
        protected int|false|null $pageSize = null,
        protected ?int $maxPageSize = null,
        protected array $filters = [],
        ?Request $request = null,
    ) {
        $this->request = $request ?? request();
        $this->sort = new Sort(
            $sort,
            $defaultSort,
            $this->request,
            self::parameter('sort_parameter', 'sort_by'),
            self::parameter('direction_parameter', 'sort_direction'),
        );
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function sort(): Sort
    {
        return $this->sort;
    }

    /** @return list<Filter> */
    public function filterDefinitions(): array
    {
        return $this->filters;
    }

    public function filters(): FilterValues
    {
        return $this->filterValues ??= FilterValues::from($this->request, $this->filters);
    }

    // Order set by code (export jobs, console): the request sort parameters are ignored
    public function sortBy(?string $attribute, string $direction = 'asc'): static
    {
        $this->sort->set($attribute, $direction);
        $this->paginator = null;

        return $this;
    }

    public function pageSize(): int
    {
        $size = $this->pageSize === null || $this->pageSize === false
            ? max(1, (int) config('grid-view.page_size', 20))
            : max(1, $this->pageSize);

        if ($this->maxPageSize === null) {
            return $size;
        }

        $requested = $this->request->query(self::parameter('page_size_parameter', 'per_page'));

        if (is_string($requested) && ctype_digit($requested)) {
            $size = (int) $requested;
        }

        return max(1, min($this->maxPageSize, $size));
    }

    public function paginator(): LengthAwarePaginator
    {
        if ($this->paginator) {
            return $this->paginator;
        }

        $pageName = self::parameter('page_parameter', 'page');
        $page = $this->request->query($pageName);

        return $this->paginator = $this->paginate($pageName, is_string($page) && ctype_digit($page) ? max(1, (int) $page) : 1)
            ->withPath($this->request->url())
            ->appends(Arr::except($this->request->query(), [$pageName]));
    }

    // Page $page of the sorted records; with pageSize false all records on one page
    abstract protected function paginate(string $pageName, int $page): LengthAwarePaginator;

    protected static function parameter(string $key, string $default): string
    {
        $name = config("grid-view.{$key}", $default);

        if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/D', $name)) {
            throw new InvalidArgumentException("Invalid grid query parameter name in [grid-view.{$key}].");
        }

        return $name;
    }
}
