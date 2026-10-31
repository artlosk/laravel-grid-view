<?php

namespace Artlosk\LaravelGridView\Tests;

use Artlosk\LaravelGridView\DataProviders\EloquentDataProvider;
use Artlosk\LaravelGridView\Formatter;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\GridViewServiceProvider;
use Artlosk\LaravelGridView\Tests\Fixtures\GridRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            GridViewServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /** @param  Router  $router */
    protected function defineRoutes($router): void
    {
        $router->get('/records/{record}', fn () => 'show')->name('records.show');
        $router->get('/records/{record}/edit', fn () => 'edit')->name('records.edit');
        $router->delete('/records/{record}', fn () => 'destroy')->name('records.destroy');
        $router->post('/records/{record}/restore', fn () => 'restore')->name('records.restore');
        $router->middleware('web')->get('/records', fn () => view('grid-test::page', [
            'provider' => new EloquentDataProvider(GridRecord::query(), sort: ['id', 'name']),
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('grid-test', __DIR__.'/Fixtures/views');
        GridView::translateLabelsUsing(null);
        Formatter::flush();

        if (!Schema::hasTable('grid_records')) {
            Schema::create('grid_records', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->integer('rank');
            });
        }
    }
}
