# PHP API

[English version](api.md)

## Таблица

```php
$grid = GridView::make(User::query(), $request, 'users-grid')
    ->columns($columns)
    ->defaultSort('created_at', 'desc')
    ->paginate(20)
    ->ajax();
```

Основные методы:

- `columns(array $columns)`
- `paginate(int $perPage, string $pageParameter = 'page')`
- `sortParameters(string $sort, string $direction)`
- `defaultSort(string $column, string $direction = 'asc')`
- `sort(string $column, string $direction = 'asc')`
- `stablePagination(bool $enabled = true, string|Closure|null $tieBreaker = null)`
- `rowExpander(RowExpander|Closure $expander)`
- `rowClass(string|Closure|null $class)`
- `emptyMessage(string $message)`
- `ajax(bool $enabled = true)`
- `sortQueryName()`
- `directionQueryName()`
- `sortableKeys()`
- `sortedQuery()`
- `records()`
- `partialResponse()`

`sort()` фиксирует порядок. Параметры сортировки из запроса игнорируются, поэтому используйте его, когда URL не должен менять порядок:

```php
$grid->sort('created_at', 'desc');
```

`sortQueryName()` и `directionQueryName()` возвращают имена query-параметров сортировки и направления. `sortableKeys()` возвращает ключи видимых колонок с сортировкой. `sortedQuery()` возвращает запрос с текущей сортировкой без пагинации.

Для стабильной пагинации после выбранной сортировки добавляется ключ модели. Для группового запроса его можно отключить:

```php
$grid->stablePagination(false);
```

## Колонки

```php
Column::make('company')
    ->label('Компания')
    ->visible(fn () => auth()->user()->can('viewCompanies'))
    ->sortable(fn ($query, $direction) => $query
        ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
        ->select('users.*')
        ->orderBy('companies.name', $direction))
    ->render(fn ($value, $user) => $user->company?->name);
```

Значения ячеек экранируются. Используйте `raw()` или `Htmlable` только для доверенного HTML.

Своя сортировка должна оставлять одну строку результата на модель. Для связи one-to-many используйте агрегат, `distinct` или подзапрос.

## Действия

```php
Action::button('edit')
    ->title('Изменить')
    ->attributes(fn ($user) => [
        'class' => 'js-edit-user',
        'data-user-id' => $user->id,
    ]);
```

Доступные типы:

- `Action::link()` для GET-ссылок
- `Action::form()` для POST, PUT, PATCH и DELETE
- `Action::button()` для JavaScript приложения

Атрибуты экранируются. Inline-атрибуты `on*` запрещены. Формы принимают только относительные URL или URL текущего сайта.

Не помещайте таблицу с действиями-формами внутрь другого `<form>`.

## Развёрнутые строки

```php
ExpandedGroup::make([
    ExpandedRow::make(['account' => 'Наличные', 'amount' => '-10.00']),
    ExpandedRow::make(['account' => 'Банк', 'amount' => '10.00']),
], sharedColumns: ['date', 'status', 'actions']);
```

В группе должен быть хотя бы один `ExpandedRow`.

## Частичные запросы

```php
if (GridRequest::targetsGrid($request, $grid->id())) {
    return $grid->partialResponse();
}
```

Для страницы с одной таблицей достаточно `wantsPartial()`.

## Настройки

Основные настройки лежат в `config/grid-view.php`: классы, имена query-параметров, размер страницы, заголовки, текст пустой таблицы и шаблон пагинации.
