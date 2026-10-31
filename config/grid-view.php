<?php

return [
    // Query string parameters.
    'sort_parameter' => 'sort_by',
    'direction_parameter' => 'sort_direction',
    'page_parameter' => 'page',
    'page_size_parameter' => 'per_page',

    // Default page size of data providers.
    'page_size' => 20,

    // Append ordering by the primary key so pages never overlap.
    'stable_pagination' => true,

    // Sorting, pagination and filters without a page reload.
    // The grid sends this header; the middleware answers with the grid fragment only.
    'ajax' => true,
    'partial_header' => 'X-Grid-View',

    // Markup.
    'grid_class' => 'lgv-grid',
    'filters_class' => 'lgv-filters',
    'filter_field_class' => 'lgv-filter',
    'filter_actions_class' => 'lgv-filter-actions',
    'apply_button_class' => 'lgv-button lgv-button-primary',
    'reset_button_class' => 'lgv-button',
    'table_container_class' => 'lgv-table-container',
    'caption_class' => 'lgv-caption',
    'table_wrapper_class' => 'lgv-table-wrap',
    'table_class' => 'lgv-table',
    'pagination_view' => 'grid-view::pagination',
    'pagination_wrapper_class' => 'lgv-pagination-wrap',

    // Formatters.
    'empty_cell' => '',
    'date_format' => 'Y-m-d',
    'datetime_format' => 'Y-m-d H:i',

    // Attributes of links built by DataColumn "url".
    'link_attributes' => [],

    // Drag handle of the "reorder" option; an icon class (e.g. "fas fa-grip-vertical") instead of the ⠿ glyph.
    'reorder_handle_class' => 'lgv-reorder-handle',
    'reorder_icon' => null,

    // ActionColumn: template, buttons and the attribute that asks for confirmation.
    'action_template' => '{view} {update} {delete}',
    'action_column_class' => 'lgv-actions',
    'confirm_attribute' => 'data-grid-confirm',
    'action_buttons' => [
        'view' => [
            'route' => 'show',
            'icon' => null,
            'attributes' => ['class' => 'lgv-action'],
        ],
        'update' => [
            'route' => 'edit',
            'icon' => null,
            'attributes' => ['class' => 'lgv-action'],
        ],
        'delete' => [
            'route' => 'destroy',
            'method' => 'DELETE',
            'icon' => null,
            'confirm' => true,
            'attributes' => ['class' => 'lgv-action lgv-action-danger'],
        ],
    ],
];
