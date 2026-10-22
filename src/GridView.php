<?php

namespace Artlosk\LaravelGridView;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Artlosk\LaravelGridView\Columns\Column;
use Artlosk\LaravelGridView\Contracts\RowExpander;
use Artlosk\LaravelGridView\Rows\ExpandedGroup;
use Artlosk\LaravelGridView\Support\EvaluatesClosures;

class GridView implements Htmlable
{
    use EvaluatesClosures;

    /** @var list<Column> */
    protected array $columns = [];

    protected ?LengthAwarePaginator $paginator = null;

    protected int $perPage;

    protected string $pageParameter;

    protected string $sortParameter;

    protected string $directionParameter;

    protected ?string $defaultSort = null;

    protected string $defaultDirection = 'asc';

    protected ?string $orderedColumn = null;

    protected ?string $orderedDirection = null;

    protected bool $stablePagination;

    protected string|Closure|null $tieBreaker = null;

    protected RowExpander|Closure|null $rowExpander = null;

    protected string|Closure|null $rowClass = null;

    protected string $emptyMessage;

    protected string $tableClass;

    protected string $gridClass;

    protected string $tableWrapperClass;

    protected string $paginationView;

    protected ?string $paginationWrapperClass;

    protected bool $ajax = false;

    protected function __construct(
        protected Builder $query,
        protected Request $request,
        protected string $id,
    ) {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $this->id)) {
            throw new InvalidArgumentException(
                'Grid ID must start with a letter and contain only letters, numbers, underscores, or hyphens.',
            );
        }

        $this->perPage = max(1, config('grid-view.per_page', 20));
        $this->stablePagination = config('grid-view.stable_pagination', true);
        $this->pageParameter = $this->validatedParameterName(config('grid-view.page_parameter', 'page'));
        $this->sortParameter = $this->validatedParameterName(config('grid-view.sort_parameter', 'sort_by'));
        $this->directionParameter = $this->validatedParameterName(
            config('grid-view.direction_parameter', 'sort_direction'),
        );

        if ($this->sortParameter === $this->directionParameter) {
            throw new InvalidArgumentException('Sort and direction parameters must be different.');
        }

        $emptyMessage = config('grid-view.empty_message');
        $this->emptyMessage = is_string($emptyMessage)
            ? $emptyMessage
            : __('grid-view::grid-view.empty');
        $this->tableClass = config('grid-view.table_class', 'lgv-table');
        $this->gridClass = config('grid-view.grid_class', 'lgv-grid');
        $this->tableWrapperClass = config('grid-view.table_wrapper_class', 'lgv-table-wrap');
        $this->paginationView = config('grid-view.pagination_view', 'grid-view::pagination');
        $paginationWrapperClass = config('grid-view.pagination_wrapper_class');
        $this->paginationWrapperClass = is_string($paginationWrapperClass) && $paginationWrapperClass !== ''
            ? $paginationWrapperClass
            : null;
    }

    public static function make(Builder $query, ?Request $request = null, string $id = 'grid-view'): static
    {
        return new static($query, $request ?? request(), $id);
    }

    /**
     * @param  list<Column>  $columns
     */
    public function columns(array $columns): static
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new InvalidArgumentException('Grid columns must be a non-empty list.');
        }

        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                throw new InvalidArgumentException('Every grid column must be a Column instance.');
            }
        }

        $this->columns = $columns;
        $this->paginator = null;

        return $this;
    }

    public function paginate(int $perPage, string $pageParameter = 'page'): static
    {
        $this->perPage = max(1, $perPage);
        $this->pageParameter = $this->validatedParameterName($pageParameter);
        $this->paginator = null;

        return $this;
    }

    public function sortParameters(string $sort, string $direction): static
    {
        $sort = $this->validatedParameterName($sort);
        $direction = $this->validatedParameterName($direction);

        if ($sort === $direction) {
            throw new InvalidArgumentException('Sort and direction parameters must be different.');
        }

        $this->sortParameter = $sort;
        $this->directionParameter = $direction;
        $this->paginator = null;

        return $this;
    }

    public function sortQueryName(): string
    {
        return $this->sortParameter;
    }

    public function directionQueryName(): string
    {
        return $this->directionParameter;
    }

    public function defaultSort(string $column, string $direction = 'asc'): static
    {
        $this->defaultSort = $column;
        $this->defaultDirection = $this->sortDirection($direction);
        $this->paginator = null;

        return $this;
    }

    public function sort(string $column, string $direction = 'asc'): static
    {
        $this->orderedColumn = $column;
        $this->orderedDirection = $this->sortDirection($direction);
        $this->paginator = null;

        return $this;
    }

    public function stablePagination(
        bool $enabled = true,
        string|Closure|null $tieBreaker = null,
    ): static
    {
        $this->stablePagination = $enabled;
        $this->tieBreaker = $tieBreaker;
        $this->paginator = null;

        return $this;
    }

    public function rowExpander(RowExpander|Closure $expander): static
    {
        $this->rowExpander = $expander;

        return $this;
    }

    public function rowClass(string|Closure|null $class): static
    {
        $this->rowClass = $class;

        return $this;
    }

    public function emptyMessage(string $message): static
    {
        $this->emptyMessage = $message;

        return $this;
    }

    public function tableClass(string $class): static
    {
        $this->tableClass = $class;

        return $this;
    }

    public function gridClass(string $class): static
    {
        $this->gridClass = $class;

        return $this;
    }

    public function tableWrapperClass(string $class): static
    {
        $this->tableWrapperClass = $class;

        return $this;
    }

    public function paginationView(string $view): static
    {
        $this->paginationView = $view;

        return $this;
    }

    public function paginationWrapperClass(?string $class): static
    {
        $this->paginationWrapperClass = $class !== '' ? $class : null;

        return $this;
    }

    public function ajax(bool $enabled = true): static
    {
        $this->ajax = $enabled;

        return $this;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function isAjax(): bool
    {
        return $this->ajax;
    }

    public function partialHeader(): string
    {
        return $this->validatedHeaderName(config('grid-view.partial_header', 'X-Grid-View-Partial'));
    }

    public function partialIdHeader(): string
    {
        return $this->validatedHeaderName(config('grid-view.partial_id_header', 'X-Grid-View-ID'));
    }

    public function tableClassName(): string
    {
        return $this->tableClass;
    }

    public function gridClassName(): string
    {
        return $this->gridClass;
    }

    public function tableWrapperClassName(): string
    {
        return $this->tableWrapperClass;
    }

    public function paginationViewName(): string
    {
        return $this->paginationView;
    }

    public function paginationWrapperClassName(): ?string
    {
        return $this->paginationWrapperClass;
    }

    public function emptyMessageText(): string
    {
        return $this->emptyMessage;
    }

    /**
     * @return Collection<int, Column>
     */
    public function visibleColumns(): Collection
    {
        return collect($this->columns)
            ->filter(fn (Column $column) => $column->isVisible($this))
            ->values();
    }

    /**
     * @return list<string>
     */
    public function sortableKeys(): array
    {
        return $this->visibleColumns()
            ->filter(fn (Column $column) => $column->isSortable())
            ->map(fn (Column $column) => $column->key())
            ->values()
            ->all();
    }

    public function sortedQuery(): Builder
    {
        $query = clone $this->query;
        $this->applySorting($query);

        return $query;
    }

    public function records(): LengthAwarePaginator
    {
        if ($this->paginator) {
            return $this->paginator;
        }

        $query = $this->sortedQuery();

        $this->paginator = $query
            ->paginate(
                $this->perPage,
                ['*'],
                $this->pageParameter,
                max(1, $this->request->integer($this->pageParameter, 1)),
            )
            ->appends($this->navigationQuery());

        return $this->paginator;
    }

    public function expandedGroup(object $record): ExpandedGroup
    {
        if ($this->rowExpander instanceof RowExpander) {
            return $this->rowExpander->expand($record);
        }

        return $this->evaluate($this->rowExpander, $record) ?? ExpandedGroup::single();
    }

    public function recordClass(object $record): ?string
    {
        return $this->evaluate($this->rowClass, $record);
    }

    public function sortColumn(): ?Column
    {
        $key = $this->requestedSort();

        return $this->visibleColumns()->first(
            fn (Column $column) => $column->key() === $key && $column->isSortable(),
        );
    }

    public function requestedSort(): ?string
    {
        if ($this->orderedColumn) {
            return $this->orderedColumn;
        }

        return $this->request->string($this->sortParameter)->toString() ?: $this->defaultSort;
    }

    public function requestedDirection(): string
    {
        if ($this->orderedDirection) {
            return $this->orderedDirection;
        }

        $direction = $this->request->string($this->directionParameter)->toString();

        return $this->sortDirection($direction ?: $this->defaultDirection);
    }

    public function sortUrl(Column $column): string
    {
        $direction = $this->requestedSort() === $column->key() && $this->requestedDirection() === 'asc'
            ? 'desc'
            : 'asc';

        $query = array_merge($this->navigationQuery(), [
            $this->sortParameter => $column->key(),
            $this->directionParameter => $direction,
            $this->pageParameter => 1,
        ]);

        return $this->request->url().'?'.http_build_query($query);
    }

    public function sortIndicator(Column $column): ?string
    {
        if ($this->requestedSort() !== $column->key()) {
            return null;
        }

        return $this->requestedDirection() === 'asc' ? 'asc' : 'desc';
    }

    public function partialResponse(): Response
    {
        $partialHeader = $this->partialHeader();
        $partialIdHeader = $this->partialIdHeader();

        if (strcasecmp($partialHeader, $partialIdHeader) === 0) {
            throw new InvalidArgumentException('Partial marker and grid ID headers must be different.');
        }

        return response($this->render())
            ->withHeaders([
                $partialHeader => '1',
                $partialIdHeader => $this->id,
                'Vary' => implode(', ', [
                    $partialHeader,
                    $partialIdHeader,
                    'X-Requested-With',
                ]),
                'Cache-Control' => 'private, no-store',
            ]);
    }

    public function render(): string
    {
        return view('grid-view::grid', ['grid' => $this])->render();
    }

    public function toHtml(): string
    {
        return $this->render();
    }

    protected function applySorting(Builder $query): void
    {
        $column = $this->sortColumn();

        if ($column) {
            $query->reorder();
            $column->applySort($query, $this->requestedDirection());
        }

        if (!$this->stablePagination) {
            return;
        }

        $tieDirection = $column ? $this->requestedDirection() : 'asc';

        if ($this->tieBreaker instanceof Closure) {
            ($this->tieBreaker)($query, $tieDirection);

            return;
        }

        $query->orderBy(
            $this->tieBreaker ?? $query->getModel()->getQualifiedKeyName(),
            $tieDirection,
        );
    }

    protected function sortDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @return array<string, mixed>
     */
    protected function navigationQuery(): array
    {
        $query = $this->request->query();

        unset($query['_grid_partial'], $query['_grid_id']);

        return $query;
    }

    protected function validatedParameterName(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/D', $name)) {
            throw new InvalidArgumentException("Invalid grid query parameter name [{$name}].");
        }

        return $name;
    }

    protected function validatedHeaderName(string $name): string
    {
        if (!preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $name)) {
            throw new InvalidArgumentException("Invalid grid header name [{$name}].");
        }

        return $name;
    }
}
