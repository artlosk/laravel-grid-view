# AJAX

[Русская версия](ajax.ru.md)

With `ajax => true` (default) sorting, pagination and the filter form redraw only the grid.

## How it works

1. The package script intercepts clicks on headers, pagination and "Reset", and the filter form submit.
2. It requests the same URL with the `X-Grid-View: <grid id>` header.
3. The page renders as usual; the `PartialGridResponse` middleware (added to the `web` group by the package) takes the grid with that id and answers with its HTML only.
4. The script replaces the grid, updates the browser URL (`history.pushState`) and dispatches `grid-view:rendered` with `detail: { id, grid, url }`.

Controllers do nothing special: they return a regular view.

If a response has no such grid (a redirect, an error, another page), the browser simply navigates to the URL. "Back" redraws grids on `popstate`.

## Setup

```blade
<script src="{{ asset('vendor/grid-view/grid-view.js') }}" defer></script>
```

or with Vite: `import '…/vendor/artlosk/laravel-grid-view/resources/js/grid-view.js'`.

## External filter form

A form outside the grid (e.g. shared by several pages) is linked with an attribute:

```blade
<form method="GET" action="{{ route('orders.index') }}" data-grid-view-filter="orders-grid">
    …
</form>

@grid(['id' => 'orders-grid', 'dataProvider' => $provider, 'showFilters' => false, 'columns' => [...]])
```

The current sorting is kept on submit.

## Re-initialization

Bring new elements (e.g. select plugins) to life in one place:

```js
document.addEventListener('grid-view:rendered', (event) => initPlugins(event.detail.grid));
```

Prefer delegated click handlers on `document`: they never need rebinding.

## Confirmation

A button with `confirm` gets `data-grid-confirm` (rename it with `confirm_attribute`). Without your own handler the package script shows `window.confirm`.

## Settings

| Key | Default | Purpose |
|---|---|---|
| `ajax` | `true` | AJAX for all grids; `'ajax' => false` per grid |
| `partial_header` | `X-Grid-View` | header of the fragment request |
