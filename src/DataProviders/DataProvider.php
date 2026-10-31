<?php

namespace Artlosk\LaravelGridView\DataProviders;

use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;
use Artlosk\LaravelGridView\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Data of a grid or an API list: filtered, sorted and paginated records.
 */
interface DataProvider
{
    public function paginator(): LengthAwarePaginator;

    public function sort(): Sort;

    /** @return list<Filter> */
    public function filterDefinitions(): array;

    public function filters(): FilterValues;

    public function request(): Request;
}
