# Look and feel

[Русская версия](customization.ru.md)

## Classes

Markup classes live in `config/grid-view.php`:

```php
'grid_class' => 'lgv-grid',
'filters_class' => 'lgv-filters',           // the filter form
'filter_field_class' => 'lgv-filter',       // field wrapper; per filter: ->fieldClass('col-md-4')
'filter_actions_class' => 'lgv-filter-actions',
'apply_button_class' => 'lgv-button lgv-button-primary',
'reset_button_class' => 'lgv-button',
'table_container_class' => 'lgv-table-container',
'caption_class' => 'lgv-caption',
'table_wrapper_class' => 'lgv-table-wrap',
'table_class' => 'lgv-table',
'pagination_view' => 'grid-view::pagination',
'pagination_wrapper_class' => 'lgv-pagination-wrap',
'link_attributes' => [],                    // attributes of links of columns with 'url'
'reorder_handle_class' => 'lgv-reorder-handle',
'reorder_icon' => null,                     // e.g. 'fas fa-grip-vertical' instead of the ⠿ glyph
```

Bootstrap example:

```php
'filters_class' => 'row align-items-end mb-3',
'filter_field_class' => 'col-md-3',
'table_wrapper_class' => 'table-responsive',
'table_class' => 'table table-hover',
'pagination_view' => 'pagination::bootstrap-5',
```

## Action buttons

```php
'action_template' => '{view} {update} {delete}',
'action_column_class' => 'lgv-actions',
'confirm_attribute' => 'data-grid-confirm',
'action_buttons' => [
    'view' => ['route' => 'show', 'icon' => 'fas fa-eye', 'attributes' => ['class' => 'btn btn-sm']],
    'update' => ['route' => 'edit', 'icon' => 'fas fa-pen'],
    'delete' => ['route' => 'destroy', 'method' => 'DELETE', 'icon' => 'fas fa-trash', 'confirm' => true],
    'restore' => ['route' => 'restore', 'method' => 'POST', 'icon' => 'fas fa-undo', 'label' => 'Restore'],
],
```

Without `icon` a button shows its label. `confirm: true` uses the default question; a string is your own text.

## Default theme colors

```css
.lgv-grid {
    --lgv-border: #dee2e6;
    --lgv-muted: #6c757d;
    --lgv-surface: transparent;
    --lgv-hover: #f8f9fa;
}
```

## Views

```bash
php artisan vendor:publish --tag=grid-view-views
```

You can override:
- `grid.blade.php` — the whole grid;
- `filters.blade.php` — the filter form;
- `filters/{text,select,date}.blade.php` — the fields;
- `actions/{link,form}.blade.php` — the buttons;
- `pagination.blade.php`.

Usually the config and the field views are enough.

## Own column type

```php
use Artlosk\LaravelGridView\Columns\DataColumn;

class MoneyColumn extends DataColumn { /* ... */ }

// ['class' => MoneyColumn::class, 'attribute' => 'amount']
```
