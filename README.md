# Laravel Grid View

[Русская версия](README.ru.md)

A Laravel table in the spirit of Yii2 `GridView`: a data provider handles the query, filters, sorting and pages; the grid is described with one array right in the view. Sorting, pagination and filters work without a page reload, and controllers need no extra code for that.

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13 (Laravel 13 needs PHP 8.3+)

## Installation

```bash
composer require artlosk/laravel-grid-view
```

Laravel discovers the service provider. Default styles and script:

```bash
php artisan vendor:publish --tag=grid-view-assets
```

```blade
<link rel="stylesheet" href="{{ asset('vendor/grid-view/grid-view.css') }}">
<script src="{{ asset('vendor/grid-view/grid-view.js') }}" defer></script>
```

With Vite, import `vendor/artlosk/laravel-grid-view/resources/js/grid-view.js` and `resources/css/grid-view.css` directly.

## Quick start

Controller:

```php
use Artlosk\LaravelGridView\DataProviders\EloquentDataProvider;
use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;

public function index()
{
    $provider = new EloquentDataProvider(
        query: fn (FilterValues $filters) => User::query()
            ->when($filters->string('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($filters->int('status'), fn ($q, $status) => $q->where('status', $status)),
        filters: [
            Filter::text('search', 'Search'),
            Filter::select('status', 'Status', [1 => 'Active', 2 => 'Inactive']),
        ],
        sort: ['id', 'name', 'created_at'],
        defaultSort: '-created_at',
        pageSize: 25,
    );

    return view('users.index', compact('provider'));
}
```

View:

```blade
@grid([
    'dataProvider' => $provider,
    'columns' => [
        'id',
        'name:text:Name',
        'email:email',
        'created_at:datetime:Created',
        ['attribute' => 'status', 'view' => 'users.status-badge'],
        ['class' => 'actions', 'route' => 'users'],
    ],
])
```

That's it:
- a filter form above the table;
- sortable `id`, `name` and `created_at` headers;
- pagination;
- view, edit and delete buttons pointing to `users.show`, `users.edit` and `users.destroy`;
- AJAX updates without a page reload.

`@grid([...])` equals `{!! GridView::widget([...]) !!}`.

## Data provider

```php
new EloquentDataProvider(
    query: $builder,              // Builder or fn (FilterValues $filters) => Builder
    filters: [...],               // declared filters: only they reach the query
    sort: [                       // sorting allow-list
        'name',                   // by the "name" column
        'balance' => 'accounts.current_balance',          // by another column
        'owner' => fn ($query, $direction) => $query->orderBy(...), // custom ordering
    ],
    defaultSort: 'name',          // '-created_at' for descending
    pageSize: 50,                 // null: config value, false: everything on one page
    maxPageSize: 100,             // let clients choose ?per_page= up to this limit (APIs)
);
```

Reuse the provider in APIs: `$provider->paginator()` returns a page of records. For background jobs use `$provider->sortBy('name', 'desc')->sortedQuery()`: the order is set by code and query parameters are ignored.

Only attributes listed in `sort` are sortable; anything else is ignored. A `sort_by` without `sort_direction` sorts ascending. Ordering by the primary key is appended so records never jump between pages; turn it off with `stable_pagination`.

Records already in memory (report rows, a response of another service) go to `ArrayDataProvider`. Rows can be arrays or objects:

```php
new ArrayDataProvider(
    models: $report['rows'],      // iterable or fn (FilterValues $filters) => iterable
    sort: [
        'name',                   // by the "name" key
        'amount' => 'totals.amount',                  // by a dotted path
        'owner' => fn ($row) => $row['owner']['name'], // by a computed value
    ],
    defaultSort: '-amount',
    pageSize: 20,
);
```

Numeric strings are compared as numbers, equal values keep their order. `$provider->sortedModels()` returns every filtered row in the current order.

## Filters

```php
Filter::text('search', 'Search')->placeholder('Name or email');
Filter::select('status', 'Status', [1 => 'Active', 2 => 'Inactive'])->default('1');
Filter::select('tags', 'Tags', $items)->multiple();
Filter::select('source', 'Source', ['Banks' => [3 => 'Cash', 4 => 'Card']]); // option group
Filter::date('from', 'From');
Filter::select('country_id', 'Country', $countries)->hidden(); // accepted, not shown
```

Values reach `query` already clean:
- text is trimmed, an array instead of a string becomes `null`;
- a select accepts only values from its items;
- a date must be a real day in `Y-m-d`.

`FilterValues` has `string()`, `int()`, `array()`, `ints()`, `all()` and `active()`. `default()` applies only when the request has no such parameter: an empty value still means "no filter".

Select items: `[value => label]` or a list of `['value' => ..., 'label' => ..., 'attributes' => [...]]`. Option groups: `['Group' => [value => label]]` or `['group' => 'Group', 'items' => [...]]`.

`hidden()` is for filters an API needs but the page does not show: the value is still read from the query string and kept in a hidden input of the form.

## Columns

Short form: `'attribute'`, `'attribute:format'`, `'attribute:format:Label'`.

```php
[
    'attribute' => 'country.name',      // value, column key and sort key
    'label' => 'Country',
    'value' => fn ($model, $row) => ..., // or a dotted path
    'format' => 'text',                 // 'raw', ['date', 'd.m.Y'], own format, fn ($value, $model) => ...
    'view' => 'partials.country',       // Blade view with $model, $value, $row
    'url' => fn ($model) => route('countries.show', $model),
    'linkOptions' => ['class' => 'link'],
    'sortable' => false,
    'headerOptions' => ['class' => 'text-end'],
    'contentOptions' => fn ($model, $row) => ['class' => 'text-end'],
    'visible' => true,
]
```

Column classes: `data` (default), `actions`, `serial` (row number) or your own subclass of `Columns\Column`. A misspelled option throws right away.

### Formats

`text` (escaped, default), `raw`, `ntext`, `boolean`, `date`, `datetime`, `number`, `email`, `url`. Empty values print `empty_cell` from the config. The `url` format links only `http(s)` and relative addresses.

Own format:

```php
Formatter::extend('money', fn ($value, $model, string $currency = 'USD') => view('partials.money', compact('value', 'currency')));
// 'balance:money' or ['attribute' => 'balance', 'format' => ['money', 'EUR']]
```

A string result is escaped; an `Htmlable` (`HtmlString`, `View`) is printed as is.

### Action buttons

```php
[
    'class' => 'actions',
    'route' => 'users',                          // users.show / users.edit / users.destroy
    'template' => '{view} {update} {delete}',
    'visibleButtons' => ['delete' => fn ($user) => $user->isActive()],
    'buttons' => [
        'delete' => ['label' => 'Deactivate', 'confirm' => 'Deactivate the user?'],
        'restore' => ['route' => 'restore', 'method' => 'POST', 'icon' => 'fa fa-undo', 'label' => 'Restore'],
    ],
    'urls' => ['view' => fn ($user) => route('profile', $user)],
]
```

A button is the preset from `grid-view.action_buttons` merged with the column `buttons`. Available keys: `route` (a suffix of the column `route` or a full name), `url`, `method`, `label`, `icon`, `confirm`, `attributes`. CSS classes are joined on merge, and `null` removes a preset attribute. Non-GET buttons render a form with `@csrf` and `@method`.

`visibleButtons` only hides buttons. Check access in policies and controllers.

## Grid options

```php
@grid([
    'dataProvider' => $provider,
    'columns' => [...],
    'id' => 'users-grid',          // stable id for AJAX, grid0, grid1… by default
    'caption' => 'Users',          // heading above the table: a string or an Htmlable
    'emptyText' => 'No users yet',
    'showFilters' => false,        // no filter form
    'ajax' => false,               // plain links instead of AJAX
    'rowClass' => fn ($user) => $user->isActive() ? null : 'muted',
    'rowExpander' => ...,          // see "Grouped rows"
    'reorder' => [...],            // see "Reordering rows"
])
```

A misspelled option throws right away. Create a `view(...)` caption inside `@grid`: a `View` passed to another view as data is rendered to a string by Laravel first, and a string caption is escaped.

## Reordering rows

Records can be dragged into a custom order, like categories in a menu:

```php
@grid([
    'dataProvider' => $provider,   // sort: ['position', ...], defaultSort: 'position', pageSize: false
    'reorder' => [
        'url' => route('categories.reorder'),
        'method' => 'PUT',         // POST by default
        'sort' => 'position',      // dragging works only while the grid is sorted by it, ascending
        'key' => 'id',             // what is sent: the primary key of a model or "id" by default
    ],
    'columns' => [...],
])
```

A handle is added as the first column (`['class' => 'reorder']` puts it elsewhere). Records move with a mouse, a finger or ↑ ↓ on a focused handle. After every move the script sends `{"ids": [...]}` with the `X-CSRF-TOKEN` from `<meta name="csrf-token">`; your route saves the order. Requests go one at a time; if one fails the grid is redrawn with the saved order and `grid-view:reorder-failed` is dispatched (`grid-view:reordered` on success).

Dragging is off while the grid shows more than one page or a filter is applied: every record has to be on the page.

## Grouped rows

One record can span several rows. Shared cells get `rowspan`, and every record gets its own `<tbody>`:

```php
'rowExpander' => fn ($invoice) => ExpandedGroup::make(
    $invoice->lines->map(fn ($line) => ExpandedRow::make(['product' => $line->name]))->all(),
    sharedColumns: ['number', 'actions'],
),
```

A value of an `ExpandedRow` (by the column key) wins over the column `value`, and the format applies to it.

## Labels and translations

```php
GridView::translateLabelsUsing(fn (string $label) => __($label));
```

The translator receives column, button and filter labels, placeholders, `caption`, `emptyText` and confirmation texts. The package's own texts are `grid-view::grid-view.*` (`en` and `ru`).

## AJAX

Details: [docs/ajax.md](docs/ajax.md). In short:
- sorting, pages, the filter form and "Reset" redraw only the grid;
- the script sends `X-Grid-View: <id>`, and the package middleware (`web` group) answers with the HTML of that grid only;
- the browser URL is updated and "Back" works;
- without JavaScript everything works as plain links.

## Look and feel

Classes, the pagination view and button presets live in `config/grid-view.php`. Publish and override the views:

```bash
php artisan vendor:publish --tag=grid-view-config
php artisan vendor:publish --tag=grid-view-views
php artisan vendor:publish --tag=grid-view-translations
```

More: [docs/customization.md](docs/customization.md).

## Upgrading from v1

v2 is not compatible with v1:
- **`GridView::make($query, $request)->columns([...])`** is replaced by a provider and `@grid([...])`.
- **`Column::make()`, `ActionColumn::make()->actions([...])`, `Action::link/form/button()`** are replaced by column arrays and button presets.
- **`GridRequest::wantsPartial()` and `partialResponse()`** are not needed anymore: the middleware builds the partial response.
- **Sorting** is set on the provider with `sort` and `defaultSort`. `->sortableKeys()` is now `$provider->sort()->attributes()`.
- **Data without HTML**: `->records()` and `->sortedQuery()` become `$provider->paginator()` and `$provider->sortedQuery()`.

## Tests

```bash
composer test
npm test
```

## Support

If the package is useful, you can support its development. Donations are voluntary and do not buy any goods or services.

| Network | Address |
|---------|---------|
| TON | `UQAIUhN2MYnoAvgOvH5FYmfKLZbSFHhfRunIFO71xIwvO7NT` |
| BTC | `bc1qfuhz4y8jq68ct9q6pzvyyunamzhkqx6ey75y5m` |
| ETH | `0x87B1F22144ce332CBF037533ccCE4039C7567274` |
| USDT (ERC-20) | `0x87B1F22144ce332CBF037533ccCE4039C7567274` |
| USDT (TRC-20) | `TMVfMAfLmNcHNxKZhaT56nkNgSqvsx5NaE` |
| USDT (TON) | `UQAIUhN2MYnoAvgOvH5FYmfKLZbSFHhfRunIFO71xIwvO7NT` |

Send USDT only in the network listed next to the address (ERC-20, TRC-20 or TON).

The package is MIT licensed.
