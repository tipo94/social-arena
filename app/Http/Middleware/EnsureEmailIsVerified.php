<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification required',
                'errors' => [
                    'email_verification' => [
                        'Your email address is not verified. Please check your email for a verification link or request a new one.'
                    ]
                ],
                'data' => [
                    'verification_required' => true,
                    'email' => $user->email,
                    'resend_url' => '/api/auth/resend-verification'
                ]
            ], 403);
        }

        return $next($request);
    }
} 