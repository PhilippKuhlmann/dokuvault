<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A locked account gets no further - not even with a session that began
 * before the lock.
 *
 * Without this, "disabled" would be half a measure: signing in would be shut,
 * but whoever is signed in would stay in until they sign out themselves -
 * possibly for days. The very case in which an account is locked (somebody
 * leaves the company) is the one in which that must not be enough.
 *
 * It sits in the "web" group and on the auth:sanctum groups of the API: both
 * ways lead through auth()->user(), and a script with the locked user's token
 * should keep working as little as their browser. The agent tokens hang on no
 * user and stay untouched - they document devices, not people.
 */
class AccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $nutzer = $request->user();

        if ($nutzer === null || ! $nutzer->isDisabled()) {
            return $next($request);
        }

        // Came in through a token: there is no session to end, and a script
        // can do nothing with a redirect.
        if ($request->expectsJson() || $request->routeIs('api.*') || $request->is('api/*')) {
            abort(403, __('Dieser Zugang ist deaktiviert.'));
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withErrors(['username' => __('Dieser Zugang ist deaktiviert. Wenden Sie sich an Ihre Administration.')]);
    }
}
