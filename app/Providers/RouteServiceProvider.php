<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/customer/search';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Agents: counted per token, not per IP. All machines of a customer
        // site report from behind the same NAT address and share one token -
        // per IP, 60 a minute stopped at about 260 agents per site (one agent
        // makes ~0.23 requests a minute: check-in every five minutes plus
        // its runs). 1200 a minute per token holds some 5000 agents.
        //
        // The second limit per IP keeps a cap on whoever tries token after
        // token: each guessed token would otherwise get a counter of its own.
        // 6000 a minute (100 a second) is still far above any real site.
        RateLimiter::for('agent', function (Request $request) {
            $token = $request->bearerToken() ?: $request->header('X-Agent-Token');

            return [
                Limit::perMinute(1200)->by('agent-token:'.hash('sha256', (string) $token)),
                Limit::perMinute(6000)->by('agent-ip:'.$request->ip()),
            ];
        });
    }
}
