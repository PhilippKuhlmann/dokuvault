<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class isTechniker
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vorher "ist Rolle 10". Eine zweite Technikergruppe haette die
        // Fernwartungs-Suche damit nie oeffnen koennen.
        abort_unless(auth()->user() && Gate::allows('remote_search'), 403);

        return $next($request);
    }
}
