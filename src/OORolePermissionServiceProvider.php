<?php

namespace OnaOnbir\OORolePermission;

use Illuminate\Support\ServiceProvider;
use OnaOnbir\OORolePermission\Middlewares\OORoleOrPermissionMiddleware;
use OnaOnbir\OORolePermission\Services\OORolePermission;
use OnaOnbir\OORolePermission\Services\TimePermissionValidator;

class OORolePermissionServiceProvider extends ServiceProvider
{
    private string $packageName = 'oo-role-permission';

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerMiddleware();
        $this->registerPublishing();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/'.$this->packageName.'.php',
            $this->packageName
        );

        $this->registerServices();
    }

    private function registerServices(): void
    {
        // Bind TimePermissionValidator as singleton
        $this->app->singleton(TimePermissionValidator::class, function ($app) {
            return new TimePermissionValidator;
        });

        // Bind the main service as singleton for better performance
        $this->app->singleton(OORolePermission::class, function ($app) {
            return new OORolePermission($app->make(TimePermissionValidator::class));
        });

        // Register helper alias
        $this->app->alias(OORolePermission::class, 'oo-role-permission');
    }

    private function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('oo_rp', OORoleOrPermissionMiddleware::class);
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], $this->packageName.'-migrations');

        $this->publishes([
            __DIR__.'/../config/'.$this->packageName.'.php' => config_path($this->packageName.'.php'),
        ], $this->packageName.'-config');
    }
}
