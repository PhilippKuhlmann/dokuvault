<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class IsTechnician
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Formerly "is role 10". A second technician group could never have
        // opened the remote-access search that way.
        abort_unless(auth()->user() && Gate::allows('remote_search'), 403);

        return $next($request);
    }
}
