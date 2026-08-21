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

        $user = auth()->user();

        // Nicht mehr "ist Rolle 1", sondern "darf ueberhaupt einen der
        // Admin-Bereiche". Welchen genau, entscheidet die can-Middleware an der
        // jeweiligen Route - hier faellt nur ab, wer gar nichts darf.
        $darf = $user && collect(array_keys(config('custom.admin_permissions')))
            ->contains(fn ($recht) => Gate::forUser($user)->allows($recht));

        abort_unless($darf, 403);

        return $next($request);
    }
}
