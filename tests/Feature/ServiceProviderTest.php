<?php

namespace Artlosk\LaravelGridView\Tests\Feature;

use Artlosk\LaravelGridView\GridViewServiceProvider;
use Artlosk\LaravelGridView\Http\PartialGridResponse;
use Artlosk\LaravelGridView\Tests\TestCase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_it_registers_views_translations_and_config(): void
    {
        $this->assertTrue(View::exists('grid-view::grid'));
        $this->assertSame('No records found.', __('grid-view::grid-view.empty'));
        $this->assertSame('sort_by', config('grid-view.sort_parameter'));
        $this->assertSame('Ничего не найдено.', __('grid-view::grid-view.empty', [], 'ru'));
    }

    public function test_it_registers_the_directive_and_the_partial_middleware(): void
    {
        $this->assertArrayHasKey('grid', Blade::getCustomDirectives());
        $this->assertContains(PartialGridResponse::class, $this->app->make(Router::class)->getMiddlewareGroups()['web']);
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
