<?php

namespace Artlosk\LaravelGridView\Http;

use Artlosk\LaravelGridView\GridView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * AJAX request of a grid (header "X-Grid-View: <grid id>"): the page renders as usual,
 * the client receives only the HTML of the requested grid. Controllers need no special code.
 */
class PartialGridResponse
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $header = config('grid-view.partial_header', 'X-Grid-View');
        $id = $request->header($header);

        if (!is_string($id) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $id)) {
            return $next($request);
        }

        GridView::capturePartial($id);

        try {
            $response = $next($request);
            $html = GridView::capturedPartial();
        } finally {
            GridView::capturePartial(null);
        }

        if ($html === null || !$response instanceof Response || !$response->isOk()) {
            return $response;
        }

        return response($html)->withHeaders([
            $header => $id,
            'Content-Type' => 'text/html; charset=UTF-8',
            'Vary' => $header,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
