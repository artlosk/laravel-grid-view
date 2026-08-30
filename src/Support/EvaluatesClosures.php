<?php

namespace Artlosk\LaravelGridView\Support;

use Closure;

trait EvaluatesClosures
{
    protected function evaluate(mixed $value, mixed ...$arguments): mixed
    {
        return $value instanceof Closure ? $value(...$arguments) : $value;
    }
}
