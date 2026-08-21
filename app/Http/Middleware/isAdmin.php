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

        // Nicht mehr "ist Rolle 1", sondern "darf ueberhaupt einen der
        // Admin-Bereiche". Welchen genau, entscheidet die can-Middleware an der
        // jeweiligen Route - hier faellt nur ab, wer gar nichts darf.
        abort_unless(auth()->user() && Gate::allows('admin_bereich'), 403);

        return $next($request);
    }
}
