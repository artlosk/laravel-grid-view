# Внешний вид

[English version](customization.md)

В пакете есть небольшая стандартная тема. Все её селекторы начинаются с `lgv-`.

## Bootstrap

```php
$grid
    ->gridClass('users-grid')
    ->tableWrapperClass('table-responsive')
    ->tableClass('table table-striped table-hover')
    ->paginationView('pagination::bootstrap-5')
    ->paginationWrapperClass('d-flex justify-content-end');
```

Если в `tableClass()` нет `lgv-table`, пакет не задаёт таблице отступы, границы и выравнивание.

## Цвета

```css
.users-grid {
    --lgv-border: #dee2e6;
    --lgv-muted: #6c757d;
    --lgv-surface: transparent;
    --lgv-hover: #f8f9fa;
}
```

## Публикация файлов

```bash
php artisan vendor:publish --tag=grid-view-views
php artisan vendor:publish --tag=grid-view-translations
php artisan vendor:publish --tag=grid-view-assets
```

После обновления используйте `--force`, только если опубликованные копии можно заменить.

Классы кнопок задаются через `Action::attributes()`. Для обёртки формы используйте `Action::formAttributes()`.
