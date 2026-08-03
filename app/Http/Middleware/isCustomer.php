<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class isCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->hasCustomer()) {
            if ($request->route()->uri == 'customer/search') {
                return redirect('/'.auth()->user()->customer->slug);
            }
            if ($request->route()->customer->id != auth()->user()->customer_id) {
                abort('403');
            }
        }

        return $next($request);
    }
}
