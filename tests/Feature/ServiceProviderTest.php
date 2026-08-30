<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Artlosk\LaravelGridView\GridViewServiceProvider;
use Artlosk\LaravelGridView\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_it_registers_views_translations_and_config(): void
    {
        $this->assertTrue(View::exists('grid-view::grid'));
        $this->assertSame('No records found.', __('grid-view::grid-view.empty'));
        $this->assertSame('sort_by', config('grid-view.sort_parameter'));
    }

    public function test_it_registers_all_publish_groups(): void
    {
        foreach ([
            'grid-view-config',
            'grid-view-views',
            'grid-view-translations',
            'grid-view-assets',
        ] as $tag) {
            $this->assertNotEmpty(
                ServiceProvider::pathsToPublish(GridViewServiceProvider::class, $tag),
                "Publish tag [{$tag}] is not registered.",
            );
        }
    }
}
