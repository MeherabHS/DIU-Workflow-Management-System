<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            // Deactivated users
            if (! $user->is_active) {
                Auth::logout();

                return redirect()->route('login')->with('error', 'Your account has been deactivated. Please contact an administrator.');
            }

            // Active users with no role — pending admin approval
            if (method_exists($user, 'getRoleNames') && $user->getRoleNames()->isEmpty()) {
                Auth::logout();

                return redirect()->route('login')->with('error', 'Your account is pending admin approval. Please wait for a role assignment before logging in.');
            }
        }

        return $next($request);
    }
}
