<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Trust proxy headers for HTTPS detection in production
        if (app()->environment('production')) {
            $proxies = explode(',', env('TRUSTED_PROXIES', '*'));
            Request::setTrustedProxies(
                $proxies,
                Request::HEADER_X_FORWARDED_ALL
            );
        }
    }
}
