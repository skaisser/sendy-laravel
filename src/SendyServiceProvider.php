<?php

namespace Skaisser\Sendy;

use Illuminate\Support\ServiceProvider;
use Skaisser\Sendy\Sendy;

/**
 * Class SendyServiceProvider
 *
 * @package Skaisser\Sendy
 */
class SendyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/sendy.php' => config_path('sendy.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/sendy.php', 'sendy'
        );

        $this->app->singleton(Sendy::class, function ($app) {
            return new Sendy($app['config']['sendy']);
        });

        // If you want to register the alias 'sendy', uncomment the following line:
        // $this->app->alias(Sendy::class, 'sendy');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [Sendy::class];
    }
}