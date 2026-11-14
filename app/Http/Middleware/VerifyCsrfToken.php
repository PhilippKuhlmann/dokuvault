<?php

namespace App\Http\Middleware;

// PreventRequestForgery is the name since Laravel 13; the old
// VerifyCsrfToken is a deprecated alias of it. Besides the token it checks
// the Sec-Fetch-Site header of the browser.
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
