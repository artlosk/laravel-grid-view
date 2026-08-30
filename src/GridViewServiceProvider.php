<?php

namespace Artlosk\LaravelGridView;

use Illuminate\Support\ServiceProvider;

class GridViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'grid-view');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'grid-view');

        $this->publishes([
            __DIR__.'/../config/grid-view.php' => config_path('grid-view.php'),
        ], 'grid-view-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/grid-view'),
        ], 'grid-view-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/grid-view'),
        ], 'grid-view-translations');

        $this->publishes([
            __DIR__.'/../resources/css/grid-view.css' => public_path('vendor/grid-view/grid-view.css'),
            __DIR__.'/../resources/js/grid-view.js' => public_path('vendor/grid-view/grid-view.js'),
        ], 'grid-view-assets');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/grid-view.php', 'grid-view');
    }
}
