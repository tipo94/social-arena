<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\UserPresenceService;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function __construct(
        private UserPresenceService $presenceService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track authenticated users
        if (Auth::check()) {
            $user = Auth::user();
            $this->trackActivity($user, $request);
        }

        return $response;
    }

    /**
     * Track user activity and update presence
     */
    private function trackActivity($user, Request $request): void
    {
        try {
            // Skip tracking for certain routes to avoid noise
            if ($this->shouldSkipTracking($request)) {
                return;
            }

            // Throttle updates to prevent excessive database writes
            $throttleKey = "track_activity_{$user->id}";
            if (Cache::has($throttleKey)) {
                return;
            }

            // Set throttle (prevent tracking for next 30 seconds)
            Cache::put($throttleKey, true, 30);

            // Mark user as online and update activity
            $this->presenceService->markUserOnline($user);

        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to track user activity', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'route' => $request->route()?->getName(),
            ]);
        }
    }

    /**
     * Determine if we should skip tracking for this request
     */
    private function shouldSkipTracking(Request $request): bool
    {
        $skipRoutes = [
            'broadcasting.auth',  // Broadcasting auth endpoint
            'sanctum.csrf-cookie', // CSRF token endpoint
        ];

        $skipPaths = [
            'broadcasting/auth',
            'sanctum/csrf-cookie',
            'api/notifications/stream', // Server-sent events
        ];

        $skipMethods = ['OPTIONS'];

        $routeName = $request->route()?->getName();
        $path = $request->path();
        $method = $request->method();

        // Skip if route name matches
        if ($routeName && in_array($routeName, $skipRoutes)) {
            return true;
        }

        // Skip if path matches
        foreach ($skipPaths as $skipPath) {
            if (str_contains($path, $skipPath)) {
                return true;
            }
        }

        // Skip if method matches
        if (in_array($method, $skipMethods)) {
            return true;
        }

        // Skip if it's a preflight request
        if ($request->isMethod('OPTIONS')) {
            return true;
        }

        return false;
    }
} 