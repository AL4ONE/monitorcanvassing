<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

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
            SymfonyRequest::setTrustedProxies(
                $proxies,
                SymfonyRequest::HEADER_X_FORWARDED_ALL
            );
        }
    }
}
