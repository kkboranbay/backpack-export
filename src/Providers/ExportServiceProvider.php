<?php

namespace Kkboranbay\BackpackExport\Providers;

use Illuminate\Support\ServiceProvider;

class ExportServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // Load Translations
        if (is_dir(resource_path('lang/vendor/backpack/backpack-export'))) {
            $this->loadTranslationsFrom(resource_path('lang/vendor/backpack/backpack-export'), 'backpack-export');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'backpack-export');
        }

        // Load Views
        if (is_dir(resource_path('views/vendor/backpack/backpack-export'))) {
            $this->loadViewsFrom(resource_path('views/vendor/backpack/backpack-export'), 'backpack-export');
        }
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'backpack-export');

        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/' => config_path('backpack/operations'),
        ], 'backpack-export-config');

        //Publish views
        $this->publishes([
            __DIR__ . '/../../resources/views/' => resource_path('views/vendor/backpack/backpack-export'),
        ], 'backpack-export-views');

        //Publish lang
        $this->publishes([
            __DIR__ . '/../../resources/lang/' => resource_path('lang/vendor/backpack/backpack-export'),
        ], 'backpack-export-translations');
    }
}
