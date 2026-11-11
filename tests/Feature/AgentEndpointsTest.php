<?php

use App\Http\Controllers\API\Agent\AgentEndpoint;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * The agent API is one controller per kind of agent (app/Http/Controllers/
 * API/Agent). It used to be a single class of 1869 lines; these tests keep
 * the new shape from quietly losing what the single class had for free.
 */
function agentRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/agent/'))
        ->values();
}

test('every agent route is answered by an agent controller', function () {
    $routes = agentRoutes();

    // 15 at the time of writing - none would mean the filter found nothing.
    expect($routes->count())->toBeGreaterThanOrEqual(15);

    foreach ($routes as $route) {
        expect(is_subclass_of($route->getControllerClass(), AgentEndpoint::class))
            ->toBeTrue($route->uri().' is not answered by an AgentEndpoint.');
    }
});

test('every agent route asks for an agent token and has the agent rate limit', function () {
    // A route added outside the group would be open to anyone - with the
    // right to write devices into a customer's documentation.
    foreach (agentRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        expect(in_array('agent', $middleware, true) || in_array(AuthenticateAgent::class, $middleware, true))
            ->toBeTrue($route->uri().' does not ask for an agent token.')
            ->and($middleware)->toContain('throttle:agent');
    }
});

test('every agent controller is reachable through a route', function () {
    // The other direction: a controller nobody routes to is dead code that
    // still looks like an open endpoint.
    $routed = agentRoutes()->map(fn ($route) => $route->getControllerClass())->unique()->all();

    $controllers = collect(glob(app_path('Http/Controllers/API/Agent/*.php')))
        ->map(fn (string $file) => 'App\\Http\\Controllers\\API\\Agent\\'.basename($file, '.php'))
        ->reject(fn (string $class) => (new ReflectionClass($class))->isAbstract())
        ->values()
        ->all();

    expect(array_values(array_diff($controllers, $routed)))->toBe([]);
});
