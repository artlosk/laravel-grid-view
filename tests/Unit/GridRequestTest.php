<?php

namespace Artlosk\LaravelGridView\Tests\Unit;

use Illuminate\Http\Request;
use Artlosk\LaravelGridView\GridRequest;
use Artlosk\LaravelGridView\Tests\TestCase;

class GridRequestTest extends TestCase
{
    public function test_it_detects_partial_requests_by_header_or_query_parameter(): void
    {
        $headerRequest = Request::create('/records', server: [
            'HTTP_X_GRID_VIEW_PARTIAL' => '1',
        ]);
        $queryRequest = Request::create('/records?_grid_partial=1');
        $fullRequest = Request::create('/records');

        $this->assertTrue(GridRequest::wantsPartial($headerRequest));
        $this->assertTrue(GridRequest::wantsPartial($queryRequest));
        $this->assertFalse(GridRequest::wantsPartial($fullRequest));
    }

    public function test_it_identifies_the_target_grid_for_multi_grid_pages(): void
    {
        $headerRequest = Request::create('/records', server: [
            'HTTP_X_GRID_VIEW_PARTIAL' => '1',
            'HTTP_X_GRID_VIEW_ID' => 'users-grid',
        ]);
        $queryRequest = Request::create('/records?_grid_partial=1&_grid_id=orders-grid');

        $this->assertSame('users-grid', GridRequest::targetId($headerRequest));
        $this->assertTrue(GridRequest::targetsGrid($headerRequest, 'users-grid'));
        $this->assertFalse(GridRequest::targetsGrid($headerRequest, 'orders-grid'));
        $this->assertSame('orders-grid', GridRequest::targetId($queryRequest));
        $this->assertTrue(GridRequest::targetsGrid($queryRequest, 'orders-grid'));
    }

    public function test_it_honors_custom_partial_headers(): void
    {
        config()->set('grid-view.partial_header', 'X-Custom-Partial');
        config()->set('grid-view.partial_id_header', 'X-Custom-Grid');

        $request = Request::create('/records', server: [
            'HTTP_X_CUSTOM_PARTIAL' => '1',
            'HTTP_X_CUSTOM_GRID' => 'custom-grid',
        ]);

        $this->assertTrue(GridRequest::wantsPartial($request));
        $this->assertTrue(GridRequest::targetsGrid($request, 'custom-grid'));
    }
}
