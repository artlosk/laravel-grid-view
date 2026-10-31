<?php

namespace Artlosk\LaravelGridView;

use Artlosk\LaravelGridView\Columns\ActionColumn;
use Artlosk\LaravelGridView\Columns\Column;
use Artlosk\LaravelGridView\Columns\DataColumn;
use Artlosk\LaravelGridView\Columns\ReorderColumn;
use Artlosk\LaravelGridView\Columns\SerialColumn;
use Artlosk\LaravelGridView\Contracts\RowExpander;
use Artlosk\LaravelGridView\DataProviders\DataProvider;
use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;
use Artlosk\LaravelGridView\Rows\ExpandedGroup;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * A table of a data provider, like GridView::widget() in Yii2.
 *
 *     @grid([
 *         'dataProvider' => $provider,
 *         'columns' => [
 *             'id',
 *             'name:text:Name',
 *             'created_at:datetime',
 *             ['attribute' => 'status', 'view' => 'users.status'],
 *             ['class' => 'actions', 'route' => 'users'],
 *         ],
 *     ])
 *
 * Options: dataProvider, columns, id, caption, emptyText, showFilters, ajax, rowExpander, rowClass, reorder.
 */
class GridView implements Htmlable
{
    protected const COLUMN_ALIASES = [
        'data' => DataColumn::class,
        'actions' => ActionColumn::class,
        'serial' => SerialColumn::class,
        'reorder' => ReorderColumn::class,
    ];

    protected const REORDER_OPTIONS = ['url', 'method', 'sort', 'key'];

    protected static int $counter = 0;

    protected static ?Closure $labelTranslator = null;

    protected static ?string $partialId = null;

    protected static ?string $partialHtml = null;

    protected DataProvider $dataProvider;

    protected string $id;

    /** @var list<Column> */
    protected array $columns = [];

    protected string|Htmlable|null $caption = null;

    protected ?string $emptyText = null;

    protected bool $showFilters = true;

    protected bool $ajax;

    protected RowExpander|Closure|null $rowExpander = null;

    protected string|Closure|null $rowClass = null;

    /** @var array{url: string, method: string, sort: ?string, key: ?string}|null */
    protected ?array $reorder = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        if (!($config['dataProvider'] ?? null) instanceof DataProvider) {
            throw new InvalidArgumentException('GridView needs a "dataProvider" option.');
        }

        $this->dataProvider = $config['dataProvider'];
        $this->id = $config['id'] ?? 'grid'.self::$counter++;
        $this->ajax = $config['ajax'] ?? (bool) config('grid-view.ajax', true);

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $this->id)) {
            throw new InvalidArgumentException('Grid id must start with a letter and contain only letters, digits, "_" or "-".');
        }

        if (isset($config['reorder'])) {
            $this->reorder = $this->reorderOptions($config['reorder']);
        }

        foreach (Arr::except($config, ['dataProvider', 'id', 'ajax', 'columns', 'reorder']) as $option => $value) {
            if (!in_array($option, ['caption', 'emptyText', 'showFilters', 'rowExpander', 'rowClass'], true)) {
                throw new InvalidArgumentException("Unknown GridView option [{$option}].");
            }

            $this->{$option} = $value;
        }

        foreach (array_values($config['columns'] ?? []) as $index => $column) {
            $this->columns[] = $this->column($column, $index);
        }

        $hasHandle = array_filter($this->columns, static fn (Column $column): bool => $column instanceof ReorderColumn) !== [];

        if ($this->reorder !== null && !$hasHandle) {
            array_unshift($this->columns, new ReorderColumn([], $this, -1));
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function widget(array $config): static
    {
        return new static($config);
    }

    // Labels, filters, placeholders and empty text go through the translator: GridView::translateLabelsUsing(fn ($s) => __($s))
    public static function translateLabelsUsing(?Closure $translator): void
    {
        self::$labelTranslator = $translator;
    }

    public static function label(string $text): string
    {
        return self::$labelTranslator ? (string) (self::$labelTranslator)($text) : $text;
    }

    // Partial response of an AJAX request: the middleware asks for a grid and takes its HTML
    public static function capturePartial(?string $id): void
    {
        self::$partialId = $id;
        self::$partialHtml = null;
    }

    public static function capturedPartial(): ?string
    {
        return self::$partialHtml;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dataProvider(): DataProvider
    {
        return $this->dataProvider;
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return array_values(array_filter($this->columns, static fn (Column $column): bool => $column->isVisible()));
    }

    /** @return list<Filter> */
    public function filters(): array
    {
        return $this->showFilters ? $this->dataProvider->filterDefinitions() : [];
    }

    public function filterValues(): FilterValues
    {
        return $this->dataProvider->filters();
    }

    public function records(): LengthAwarePaginator
    {
        return $this->dataProvider->paginator();
    }

    public function expandedGroup(mixed $model): ExpandedGroup
    {
        $group = $this->rowExpander instanceof RowExpander
            ? $this->rowExpander->expand($model)
            : ($this->rowExpander instanceof Closure ? ($this->rowExpander)($model) : null);

        return $group ?? ExpandedGroup::single();
    }

    public function rowClass(mixed $model): ?string
    {
        return $this->rowClass instanceof Closure ? ($this->rowClass)($model) : $this->rowClass;
    }

    /**
     * Columns with a cell in this row of a group: shared cells exist only in the first row
     *
     * @return list<Column>
     */
    public function rowColumns(ExpandedGroup $group, int $rowIndex): array
    {
        return array_values(array_filter(
            $this->columns(),
            static fn (Column $column): bool => $rowIndex === 0 || !$group->shares($column->key()),
        ));
    }

    /** @return array<string, mixed> */
    public function cellAttributes(Column $column, mixed $model, ExpandedRow $row, ExpandedGroup $group): array
    {
        $attributes = $column->contentAttributes($model, $row);

        if ($group->rowCount() > 1 && $group->shares($column->key())) {
            $attributes['rowspan'] = $group->rowCount();
        }

        return $attributes;
    }

    /**
     * Records can be dragged: the "reorder" option is set, every record is on this page,
     * and the grid shows the order being edited (its "sort" attribute ascending, or any order without one)
     */
    public function isReorderable(): bool
    {
        if ($this->reorder === null || $this->records()->hasPages()) {
            return false;
        }

        $sort = $this->dataProvider->sort();

        return $this->reorder['sort'] === null || ($sort->column() === $this->reorder['sort'] && $sort->direction() === 'asc');
    }

    public function reorderUrl(): ?string
    {
        return $this->reorder['url'] ?? null;
    }

    public function reorderMethod(): string
    {
        return $this->reorder['method'] ?? 'POST';
    }

    // Key sent in the new order: the "key" path of the reorder option, the primary key of a model or "id"
    public function recordKey(mixed $model): mixed
    {
        if ($this->reorder['key'] ?? null) {
            return data_get($model, $this->reorder['key']);
        }

        return $model instanceof Model ? $model->getKey() : data_get($model, 'id');
    }

    // Heading above the table: a string goes through the label translator, Htmlable is printed as is
    public function caption(): string|Htmlable|null
    {
        return is_string($this->caption) ? self::label($this->caption) : $this->caption;
    }

    public function emptyText(): string
    {
        return $this->emptyText === null ? __('grid-view::grid-view.empty') : self::label($this->emptyText);
    }

    public function isAjax(): bool
    {
        return $this->ajax;
    }

    public function sortUrl(Column $column): string
    {
        return $this->url([
            ...Arr::except($this->query(), [$this->pageParameter()]),
            ...$this->dataProvider->sort()->linkQuery($column->key()),
        ]);
    }

    /** @return 'asc'|'desc'|null */
    public function sortDirection(Column $column): ?string
    {
        return $column->isSortable() ? $this->dataProvider->sort()->directionOf($column->key()) : null;
    }

    /**
     * Sort parameters kept when the filter form is submitted or reset
     *
     * @return array<string, string>
     */
    public function sortQuery(): array
    {
        $sort = $this->dataProvider->sort();

        return array_filter(
            Arr::only($this->query(), [$sort->sortParameter(), $sort->directionParameter()]),
            'is_string',
        );
    }

    public function filterAction(): string
    {
        return $this->dataProvider->request()->url();
    }

    public function resetUrl(): string
    {
        return $this->url($this->sortQuery());
    }

    public function render(): string
    {
        $html = view('grid-view::grid', ['grid' => $this])->render();

        if (self::$partialId === $this->id) {
            self::$partialHtml = $html;
        }

        return $html;
    }

    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * @param  string|array<string, mixed>|Column  $config
     */
    protected function column(string|array|Column $config, int $index): Column
    {
        if ($config instanceof Column) {
            return $config;
        }

        if (is_string($config)) {
            $config = DataColumn::parse($config);
        }

        $class = $config['class'] ?? DataColumn::class;
        $class = self::COLUMN_ALIASES[$class] ?? $class;

        if (!is_string($class) || !is_subclass_of($class, Column::class)) {
            throw new InvalidArgumentException('Grid column class must extend '.Column::class.'.');
        }

        return new $class(Arr::except($config, ['class']), $this, $index);
    }

    /**
     * @return array{url: string, method: string, sort: ?string, key: ?string}
     */
    protected function reorderOptions(mixed $options): array
    {
        $options = is_string($options) ? ['url' => $options] : $options;

        if (!is_array($options) || !is_string($options['url'] ?? null)) {
            throw new InvalidArgumentException('GridView "reorder" needs a "url".');
        }

        foreach (array_keys($options) as $key) {
            if (!in_array($key, self::REORDER_OPTIONS, true)) {
                throw new InvalidArgumentException("Unknown reorder option [{$key}].");
            }
        }

        return [
            'url' => $options['url'],
            'method' => strtoupper($options['method'] ?? 'POST'),
            'sort' => $options['sort'] ?? null,
            'key' => $options['key'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    protected function query(): array
    {
        return $this->dataProvider->request()->query();
    }

    protected function pageParameter(): string
    {
        return config('grid-view.page_parameter', 'page');
    }

    /** @param  array<string, mixed>  $query */
    protected function url(array $query): string
    {
        $url = $this->dataProvider->request()->url();

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
