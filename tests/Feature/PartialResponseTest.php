<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Artlosk\LaravelGridView\Tests\Fixtures\GridRecord;
use Artlosk\LaravelGridView\Tests\TestCase;

class PartialResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        GridRecord::query()->create(['name' => 'Alice', 'rank' => 1]);
    }

    public function test_grid_header_returns_only_the_grid(): void
    {
        $response = $this->get('/records', ['X-Grid-View' => 'records']);

        $response->assertOk()->assertHeader('X-Grid-View', 'records');
        $this->assertStringStartsWith('<div', ltrim($response->getContent()));
        $this->assertStringContainsString('id="records"', $response->getContent());
        $this->assertStringNotContainsString('Records page', $response->getContent());
    }

    public function test_without_header_or_for_another_grid_the_page_is_full(): void
    {
        $this->get('/records')->assertOk()->assertSee('Records page');
        $this->get('/records', ['X-Grid-View' => 'other'])->assertOk()->assertSee('Records page');
        $this->get('/records', ['X-Grid-View' => '<bad>'])->assertOk()->assertSee('Records page');
    }
}
