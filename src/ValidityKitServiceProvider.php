<?php

namespace ValidityKit;

use Illuminate\Support\ServiceProvider;

class ValidityKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/validity-kit.php',
            'validity-kit'
        );

        $this->app->singleton(
            LicenseValidator::class,
            function () {
                return new LicenseValidator();
            }
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/validity-kit.php'
            => config_path('validity-kit.php'),
        ], 'validity-kit-config');

        $this->app['router']->aliasMiddleware(
            'license',
            \ValidityKit\Middleware\ValidateLicense::class
        );

        if (config('validity-kit.middleware.enabled', true)) {
            $kernel = $this->app->make(
                \Illuminate\Contracts\Http\Kernel::class
            );

            $kernel->pushMiddleware(
                \ValidityKit\Middleware\ValidateLicense::class
            );
        }
    }
}
