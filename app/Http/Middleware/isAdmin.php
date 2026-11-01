<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class isAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        // No longer "is role 1" but "may enter any of the admin areas at
        // all". Which one exactly is decided by the can middleware on the
        // jeweiligen Route - hier faellt nur ab, wer gar nichts darf.
        abort_unless(auth()->user() && Gate::allows('admin_bereich'), 403);

        return $next($request);
    }
}
