# Appearance

[Русская версия](customization.ru.md)

The package has a small default theme. All selectors start with `lgv-`.

## Use Bootstrap

```php
$grid
    ->gridClass('users-grid')
    ->tableWrapperClass('table-responsive')
    ->tableClass('table table-striped table-hover')
    ->paginationView('pagination::bootstrap-5')
    ->paginationWrapperClass('d-flex justify-content-end');
```

When `tableClass()` does not contain `lgv-table`, the default table spacing, borders, and alignment are not applied.

## Change colors

```css
.users-grid {
    --lgv-border: #dee2e6;
    --lgv-muted: #6c757d;
    --lgv-surface: transparent;
    --lgv-hover: #f8f9fa;
}
```

## Publish files

```bash
php artisan vendor:publish --tag=grid-view-views
php artisan vendor:publish --tag=grid-view-translations
php artisan vendor:publish --tag=grid-view-assets
```

Use `--force` after an update only if replacing your published copies is safe.

Buttons receive classes through `Action::attributes()`. Form wrappers use `Action::formAttributes()`.
