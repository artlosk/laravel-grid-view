# AJAX

[English version](ajax.md)

AJAX можно не подключать. Без JavaScript сортировка и пагинация работают как обычные ссылки.

## Подключение

```php
$grid->ajax();
```

```blade
<link rel="stylesheet" href="{{ asset('vendor/grid-view/grid-view.css') }}">
<script src="{{ asset('vendor/grid-view/grid-view.js') }}" defer></script>
```

Контроллер должен вернуть запрошенную таблицу:

```php
if (GridRequest::wantsPartial($request)) {
    return $grid->partialResponse();
}
```

## Фильтры

```blade
<form method="GET" data-grid-view-filter="users-grid">
    <input name="search" value="{{ request('search') }}">
    <button type="submit">Найти</button>
</form>
```

Форма должна использовать GET и содержать ID AJAX-таблицы. Если форма не передаёт параметры сортировки и направления, текущая сортировка из URL сохраняется.

## Несколько таблиц

Задайте каждой таблице свой ID и свои query-параметры:

```php
$usersGrid
    ->sortParameters('users_sort', 'users_direction')
    ->paginate(20, 'users_page');

$ordersGrid
    ->sortParameters('orders_sort', 'orders_direction')
    ->paginate(20, 'orders_page');
```

Возвращайте таблицу, указанную в запросе:

```php
if (GridRequest::targetsGrid($request, $usersGrid->id())) {
    return $usersGrid->partialResponse();
}

if (GridRequest::targetsGrid($request, $ordersGrid->id())) {
    return $ordersGrid->partialResponse();
}
```

## После обновления

Скрипт отправляет событие `grid-view:rendered`:

```js
document.addEventListener('grid-view:rendered', (event) => {
    initRowButtons(event.detail.grid);
});
```

В `event.detail` находятся `id`, `grid` и `url`.

## Правила запросов

- Одинаковый незавершённый запрос отправляется один раз.
- Таблица принимает только отмеченный HTML-ответ со своим ID.
- При ошибке страница открывается обычным способом.
- Частичные ответы используют `private, no-store`.
- Заголовок `Vary` отделяет страницу от фрагмента таблицы.

Rate limit и права доступа по-прежнему настраиваются на сервере.
