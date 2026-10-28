<?php

use App\Http\Kernel;
use App\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;

// Second line of defence behind PinRootUrl: a request whose Host is not
// APP_URL's is refused before the application sees it. Laravel skips the
// middleware in tests and with APP_ENV=local, so these tests force it on.

function enforcedTrustHosts(): TrustHosts
{
    return new class(app()) extends TrustHosts
    {
        protected function shouldSpecifyTrustedHosts()
        {
            return true;
        }
    };
}

function hostSeen(string $url): string
{
    return enforcedTrustHosts()->handle(Request::create($url), fn (Request $request) => response($request->getHost()))->getContent();
}

beforeEach(fn () => config(['app.url' => 'https://doku.kuhlmannit.de']));
afterEach(fn () => Request::setTrustedHosts([]));

test('the host of APP_URL and its subdomains are accepted', function () {
    expect(hostSeen('https://doku.kuhlmannit.de/login'))->toBe('doku.kuhlmannit.de')
        ->and(hostSeen('https://www.doku.kuhlmannit.de/login'))->toBe('www.doku.kuhlmannit.de');
});

test('any other host is refused', function (string $url) {
    expect(fn () => hostSeen($url))->toThrow(SuspiciousOperationException::class);
})->with([
    'foreign domain' => ['https://evil.example/login'],
    'look-alike suffix' => ['https://doku.kuhlmannit.de.evil.example/login'],
    'bare IP' => ['http://10.10.250.91/login'],
]);

test('the middleware is part of every request', function () {
    expect(app(Kernel::class)->hasMiddleware(TrustHosts::class))->toBeTrue();
});
