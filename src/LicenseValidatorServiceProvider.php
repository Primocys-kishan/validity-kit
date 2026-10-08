<?php

namespace Primocys\LicenseValidator;

use Illuminate\Support\ServiceProvider;

class LicenseValidatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/license-validator.php',
            'license-validator'
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
            __DIR__ . '/../config/license-validator.php'
            => config_path('license-validator.php'),
        ], 'license-validator-config');

        if (config('license-validator.middleware.enabled', true)) {
            $kernel = $this->app->make(
                \Illuminate\Contracts\Http\Kernel::class
            );

            $kernel->pushMiddleware(
                \Primocys\LicenseValidator\Middleware\ValidateLicense::class
            );
        }
    }
}
