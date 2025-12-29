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
        // Auto-create storage link if it doesn't exist (for Railway deployment)
        // This will automatically create the symlink when the app starts
        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');

        // Check if storage link doesn't exist or is broken
        if (!File::exists($linkPath) || (!is_link($linkPath) && File::isDirectory($linkPath))) {
            try {
                // Remove broken link or directory if exists
                if (File::exists($linkPath)) {
                    if (is_link($linkPath)) {
                        File::delete($linkPath);
                    } elseif (File::isDirectory($linkPath)) {
                        File::deleteDirectory($linkPath);
                    } else {
                        File::delete($linkPath);
                    }
                }

                // Ensure target directory exists
                if (!File::exists($targetPath)) {
                    File::makeDirectory($targetPath, 0755, true);
                }

                // Create symlink
                if (PHP_OS_FAMILY === 'Windows') {
                    // Windows doesn't support symlinks easily, use junction or copy
                    // For Railway (Linux), this will work
                    symlink($targetPath, $linkPath);
                } else {
                    // Linux/Unix - create symlink
                    symlink($targetPath, $linkPath);
                }
            } catch (\Exception $e) {
                // Silently fail if can't create link (permissions issue)
                // Log error but don't break the app
                \Log::warning('Failed to create storage link: ' . $e->getMessage());
            }
        }
    }
}
