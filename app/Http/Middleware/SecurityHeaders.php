<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and add security headers to response.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Remove X-Powered-By header to avoid exposing server info
        $response->headers->remove('X-Powered-By');

        // Prevent browsers from guessing the MIME type
        $response->header('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking attacks
        $response->header('X-Frame-Options', 'SAMEORIGIN');

        // Enable XSS protection in older browsers
        $response->header('X-XSS-Protection', '1; mode=block');

        // Strict Transport Security (HTTPS only) - 1 year
        // Only applied in production environment
        if (app()->environment('production')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Referrer Policy - send minimal referrer
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature Policy)
        // Restrict access to sensitive features
        $response->header('Permissions-Policy', implode(',', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
        ]));

        // Content Security Policy (CSP)
        // Customize based on your app needs
        $csp = $this->buildCSP($request);
        if ($csp) {
            // Use report-only in development to see violations without blocking
            $header = app()->environment('production') ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
            $response->header($header, $csp);
        }

        return $response;
    }

    /**
     * Build Content Security Policy header
     */
    private function buildCSP(Request $request): ?string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",  // Vite requires unsafe-inline/eval during dev
            "style-src 'self' 'unsafe-inline'",                  // Vite injects styles
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' https:",
            "media-src 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        // Add S3 endpoints for assets in production
        if (app()->environment('production')) {
            $s3Endpoint = config('filesystems.disks.s3.endpoint');
            if ($s3Endpoint) {
                $directives[] = "img-src 'self' data: https: {$s3Endpoint}";
                $directives[] = "connect-src 'self' https: {$s3Endpoint}";
            }
        }

        return implode('; ', $directives);
    }
}
