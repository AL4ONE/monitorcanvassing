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
        // Bit mask 31 = HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_HOST | 
        //              HEADER_X_FORWARDED_PROTO | HEADER_X_FORWARDED_PORT | HEADER_X_FORWARDED_FORWARDED
        if (app()->environment('production')) {
            $proxies = explode(',', env('TRUSTED_PROXIES', '*'));
            SymfonyRequest::setTrustedProxies($proxies, 31);
        }
    }
}
