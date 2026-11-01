<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * From the configuration instead of hard-wired: which proxy stands in
     * front is a property of the installation, not of the application.
     * Through the configuration and not through env(), because the deploy
     * ausfuehrt - danach liefert env() nichts mehr.
     */
    public function __construct()
    {
        $proxies = config('custom.trusted_proxies');

        $this->proxies = $proxies === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', (string) $proxies))));
    }
}
