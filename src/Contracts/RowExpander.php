<?php

namespace Artlosk\LaravelGridView\Contracts;

use Artlosk\LaravelGridView\Rows\ExpandedGroup;

interface RowExpander
{
    public function expand(mixed $record): ExpandedGroup;
}
