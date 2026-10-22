# AJAX

[Русская версия](ajax.ru.md)

AJAX is optional. Sorting and pagination still work as normal links without JavaScript.

## Setup

```php
$grid->ajax();
```

```blade
<link rel="stylesheet" href="{{ asset('vendor/grid-view/grid-view.css') }}">
<script src="{{ asset('vendor/grid-view/grid-view.js') }}" defer></script>
```

Return the requested grid from the controller:

```php
if (GridRequest::wantsPartial($request)) {
    return $grid->partialResponse();
}
```

## Filters

```blade
<form method="GET" data-grid-view-filter="users-grid">
    <input name="search" value="{{ request('search') }}">
    <button type="submit">Search</button>
</form>
```

The form must use GET and point to an AJAX grid ID. If the form does not send the sort and direction parameters, the current sort from the URL is kept.

## Several grids

Give every grid its own ID and query parameters:

```php
$usersGrid
    ->sortParameters('users_sort', 'users_direction')
    ->paginate(20, 'users_page');

$ordersGrid
    ->sortParameters('orders_sort', 'orders_direction')
    ->paginate(20, 'orders_page');
```

Return the grid named in the request:

```php
if (GridRequest::targetsGrid($request, $usersGrid->id())) {
    return $usersGrid->partialResponse();
}

if (GridRequest::targetsGrid($request, $ordersGrid->id())) {
    return $ordersGrid->partialResponse();
}
```

## After an update

The script emits `grid-view:rendered`:

```js
document.addEventListener('grid-view:rendered', (event) => {
    initRowButtons(event.detail.grid);
});
```

`event.detail` contains `id`, `grid`, and `url`.

## Request rules

- Repeated pending URLs are sent once.
- A grid accepts only a marked HTML response with its own ID.
- A bad response opens the page normally.
- Partial responses use `private, no-store`.
- `Vary` separates full pages from table fragments.

Rate limits and permissions still belong on the server.
