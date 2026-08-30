<?php

namespace Artlosk\LaravelGridView\Contracts;

use Artlosk\LaravelGridView\Rows\ExpandedGroup;

interface RowExpander
{
    public function expand(object $record): ExpandedGroup;
}
