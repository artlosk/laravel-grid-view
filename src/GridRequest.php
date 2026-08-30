<?php

namespace Artlosk\LaravelGridView;

use Illuminate\Http\Request;
use InvalidArgumentException;

class GridRequest
{
    public static function wantsPartial(Request $request): bool
    {
        $header = self::headerName('partial_header', 'X-Grid-View-Partial');

        return $request->boolean('_grid_partial') || $request->header($header) === '1';
    }

    public static function targetId(Request $request): ?string
    {
        $header = self::headerName('partial_id_header', 'X-Grid-View-ID');
        $id = $request->header($header) ?? $request->query('_grid_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function targetsGrid(Request $request, string $id): bool
    {
        return self::wantsPartial($request) && self::targetId($request) === $id;
    }

    protected static function headerName(string $configKey, string $default): string
    {
        $name = config("grid-view.{$configKey}", $default);

        if (!preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $name)) {
            throw new InvalidArgumentException("Invalid grid header name [{$name}].");
        }

        return $name;
    }
}
