# PHP API

[Русская версия](api.ru.md)

## Grid

```php
$grid = GridView::make(User::query(), $request, 'users-grid')
    ->columns($columns)
    ->defaultSort('created_at', 'desc')
    ->paginate(20)
    ->ajax();
```

Main methods:

- `columns(array $columns)`
- `paginate(int $perPage, string $pageParameter = 'page')`
- `sortParameters(string $sort, string $direction)`
- `defaultSort(string $column, string $direction = 'asc')`
- `stablePagination(bool $enabled = true, string|Closure|null $tieBreaker = null)`
- `rowExpander(RowExpander|Closure $expander)`
- `rowClass(string|Closure|null $class)`
- `emptyMessage(string $message)`
- `ajax(bool $enabled = true)`
- `records()`
- `partialResponse()`

Stable pagination adds the model key after the selected sort. Turn it off for a grouped query that cannot sort by that key:

```php
$grid->stablePagination(false);
```

## Columns

```php
Column::make('company')
    ->label('Company')
    ->visible(fn () => auth()->user()->can('viewCompanies'))
    ->sortable(fn ($query, $direction) => $query
        ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
        ->select('users.*')
        ->orderBy('companies.name', $direction))
    ->render(fn ($value, $user) => $user->company?->name);
```

Cell values are escaped. Use `raw()` or return `Htmlable` only for HTML you trust.

A custom sort must keep one result row per model. For a one-to-many relation, use an aggregate, `distinct`, or a subquery.

## Actions

```php
Action::button('edit')
    ->title('Edit')
    ->attributes(fn ($user) => [
        'class' => 'js-edit-user',
        'data-user-id' => $user->id,
    ]);
```

Available types:

- `Action::link()` for GET links
- `Action::form()` for POST, PUT, PATCH, and DELETE
- `Action::button()` for application JavaScript

Attributes are escaped. Inline `on*` attributes are rejected. Forms accept relative or same-origin URLs only.

Do not place a grid with form actions inside another `<form>`.

## Expanded rows

```php
ExpandedGroup::make([
    ExpandedRow::make(['account' => 'Cash', 'amount' => '-10.00']),
    ExpandedRow::make(['account' => 'Bank', 'amount' => '10.00']),
], sharedColumns: ['date', 'status', 'actions']);
```

An expanded group needs at least one `ExpandedRow`.

## Partial requests

```php
if (GridRequest::targetsGrid($request, $grid->id())) {
    return $grid->partialResponse();
}
```

Use `wantsPartial()` when the page has one grid.

## Configuration

See `config/grid-view.php`. Classes, query parameter names, page size, headers, default text, and pagination view can be changed globally.
