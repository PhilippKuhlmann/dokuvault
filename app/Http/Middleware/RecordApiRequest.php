<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts API requests per hour and endpoint (api_request_stats), for the
 * load overview in the admin area.
 *
 * Counted in terminate(), after the answer went out: the agent does not
 * wait for the statistics. And never at the price of the request - a
 * failing count is dropped silently.
 */
class RecordApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $start = $request->server('REQUEST_TIME_FLOAT') ?: (defined('LARAVEL_START') ? LARAVEL_START : microtime(true));
            $dauer = max(0, (int) round((microtime(true) - (float) $start) * 1000));
            $pfad = mb_substr($request->route()?->uri() ?? $request->path(), 0, 150);
            $fehler = $response->getStatusCode() >= 500 ? 1 : 0;

            DB::table('api_request_stats')->upsert(
                [[
                    'stunde' => now()->startOfHour(),
                    'art' => str_starts_with($pfad, 'api/agent') ? 'agent' : 'api',
                    'pfad' => $pfad,
                    'anzahl' => 1,
                    'fehler' => $fehler,
                    'dauer_ms_summe' => $dauer,
                    'dauer_ms_max' => $dauer,
                ]],
                ['stunde', 'art', 'pfad'],
                [
                    'anzahl' => DB::raw('anzahl + 1'),
                    'fehler' => DB::raw('fehler + '.$fehler),
                    'dauer_ms_summe' => DB::raw('dauer_ms_summe + '.$dauer),
                    'dauer_ms_max' => DB::raw('CASE WHEN dauer_ms_max > '.$dauer.' THEN dauer_ms_max ELSE '.$dauer.' END'),
                ]
            );
        } catch (\Throwable) {
            // Statistics must never break or slow down the API.
        }
    }
}
