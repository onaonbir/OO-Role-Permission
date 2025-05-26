<?php

namespace OnaOnbir\OORolePermission;

use Illuminate\Support\ServiceProvider;

class OORolePermissionServiceProvider extends ServiceProvider
{
    private string $packageName = 'oo-role-permission';

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/'.$this->packageName.'.php',
            $this->packageName
        );

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], $this->packageName.'-migrations');

        $this->publishes([
            __DIR__.'/../config/'.$this->packageName.'.php' => config_path($this->packageName.'.php'),
        ], $this->packageName.'-config');

    }
}
