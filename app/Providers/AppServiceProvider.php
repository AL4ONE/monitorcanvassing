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
        // Force HTTPS scheme in production to avoid mixed content
        if (app()->environment('production')) {
            \URL::forceScheme('https');
        }

        // Auto-create storage link if it doesn't exist (for Railway deployment)
        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');

        if (!File::exists($linkPath) || (!is_link($linkPath) && File::isDirectory($linkPath))) {
            try {
                if (File::exists($linkPath)) {
                    if (is_link($linkPath)) {
                        File::delete($linkPath);
                    } elseif (File::isDirectory($linkPath)) {
                        File::deleteDirectory($linkPath);
                    } else {
                        File::delete($linkPath);
                    }
                }
                if (!File::exists($targetPath)) {
                    File::makeDirectory($targetPath, 0755, true);
                }
                // Restore Windows-specific logic
                if (PHP_OS_FAMILY === 'Windows') {
                    // Windows: use junction or fallback
                    symlink($targetPath, $linkPath);
                } else {
                    // Linux/Unix: standard symlink
                    symlink($targetPath, $linkPath);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to create storage link: ' . $e->getMessage());
            }
        }
    }
}
