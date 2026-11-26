<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings the public demo must not let anyone change: everyone signs in
 * with the same admin account. Mail and directory servers, the remote
 * pattern and the backup would let a visitor send mail, reach the internal
 * network from the demo server or take its .env - and spoil the demo for
 * everyone else until the next hourly reset. The pages stay visible.
 */
class BlockInDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if((bool) config('app.demo'), 403, __('In der Demo ist das gesperrt.'));

        return $next($request);
    }
}
