<?php

use App\Models\Accesspoint;
use App\Models\Customer;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Security audit findings: the legacy REST API checked no role rights (a
// read-only user could create and delete), resolved sites and accesspoints
// without the customer of the URL (foreign records behind the own customer),
// and took app tokens that anyone gets with their own password.

/** An integration token as the admin area creates it (ability "*"). */
function legacyToken(array $permissions, ?Customer $customer = null): string
{
    $user = userWithPermissions($permissions);
    $user->forceFill(['customer_id' => $customer?->id])->save();

    return $user->fresh()->createToken('Integration')->plainTextToken;
}

function legacyRequest(string $token, string $method, string $uri, array $data = [])
{
    app('auth')->forgetGuards();

    return test()->withToken($token)->json($method, $uri, $data);
}

function foreignSetup(): array
{
    $own = Customer::factory()->create();
    $other = Customer::factory()->create();
    $ownSite = Site::factory()->create(['customer_id' => $own->id]);
    $otherSite = Site::factory()->create(['customer_id' => $other->id, 'name' => 'FREMDER-STANDORT']);
    $otherAp = Accesspoint::factory()->create(['customer_id' => $other->id, 'site_id' => $otherSite->id, 'name' => 'FREMDER-AP']);

    return [$own, $ownSite, $otherSite, $otherAp];
}

// --- Tenant -------------------------------------------------------------------

test('a foreign accesspoint behind the own customer URL is a 404 for every verb', function () {
    [$own, $ownSite, , $otherAp] = foreignSetup();
    $token = legacyToken(['accesspoint_viewAny', 'accesspoint_update', 'accesspoint_delete'], $own);
    $uri = "/api/{$own->slug}/accesspoints/{$otherAp->id}";

    legacyRequest($token, 'GET', $uri)->assertNotFound();
    legacyRequest($token, 'PUT', $uri, ['site_id' => $ownSite->id, 'name' => 'GEKAPERT'])->assertNotFound();
    legacyRequest($token, 'DELETE', $uri)->assertNotFound();

    $fresh = Accesspoint::withTrashed()->find($otherAp->id);
    expect($fresh->name)->toBe('FREMDER-AP')
        ->and($fresh->trashed())->toBeFalse()
        ->and($fresh->site_id)->not->toBe($ownSite->id);
});

test('a foreign site behind the own customer URL is a 404', function () {
    [$own, , $otherSite] = foreignSetup();
    $token = legacyToken(['site_viewAny'], $own);

    legacyRequest($token, 'GET', "/api/{$own->slug}/sites/{$otherSite->id}")
        ->assertNotFound()
        ->assertDontSee('FREMDER-STANDORT');
});

// --- Rights -------------------------------------------------------------------

test('a read-only token cannot create, change or delete anything', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $ap = Accesspoint::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);
    $server = rackTestServer($customer, $site);
    $token = legacyToken(['site_viewAny', 'accesspoint_viewAny', 'server_viewAny']);

    legacyRequest($token, 'POST', '/api/customers', ['name' => 'Neu'])->assertForbidden();
    legacyRequest($token, 'POST', "/api/{$customer->slug}/sites", ['name' => 'Neu'])->assertForbidden();
    legacyRequest($token, 'POST', "/api/{$customer->slug}/accesspoints", ['site_id' => $site->id, 'name' => 'Neu'])->assertForbidden();
    legacyRequest($token, 'PUT', "/api/{$customer->slug}/accesspoints/{$ap->id}", ['site_id' => $site->id, 'name' => 'Neu'])->assertForbidden();
    legacyRequest($token, 'DELETE', "/api/{$customer->slug}/accesspoints/{$ap->id}")->assertForbidden();
    legacyRequest($token, 'DELETE', "/api/servers/{$server->id}")->assertForbidden();

    expect(Customer::count())->toBe(1)
        ->and(Site::count())->toBe(1)
        ->and(Accesspoint::count())->toBe(1)
        ->and($ap->fresh()->name)->not->toBe('Neu')
        ->and(Server::count())->toBe(1);

    // Reading still works.
    legacyRequest($token, 'GET', "/api/{$customer->slug}/accesspoints")->assertOk();
});

test('with the matching rights the API works as before', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $token = legacyToken(['accesspoint_create', 'accesspoint_delete', 'site_create']);

    $created = legacyRequest($token, 'POST', "/api/{$customer->slug}/accesspoints", ['site_id' => $site->id, 'name' => 'AP-Neu'])
        ->assertCreated();
    legacyRequest($token, 'POST', "/api/{$customer->slug}/sites", ['name' => 'Lager'])->assertCreated();
    legacyRequest($token, 'DELETE', "/api/{$customer->slug}/accesspoints/{$created->json('id')}")->assertNoContent();

    expect(Accesspoint::withTrashed()->find($created->json('id'))->trashed())->toBeTrue();
});

test('creating customers needs admin_customer', function () {
    legacyRequest(legacyToken(['admin_customer']), 'POST', '/api/customers', ['name' => 'Neukunde'])->assertCreated();

    expect(Customer::where('name', 'Neukunde')->exists())->toBeTrue();
});

// --- Tokens -------------------------------------------------------------------

test('an app token does not open the legacy API', function () {
    $user = userWithPermissions(['server_viewAny', 'server_delete']);
    $token = $user->createToken('App iPhone', ['mobil'])->plainTextToken;

    legacyRequest($token, 'GET', '/api/customers')->assertForbidden();
    legacyRequest($token, 'GET', '/api/servers')->assertForbidden();
});

// --- Guard --------------------------------------------------------------------

test('every legacy API route refuses a token without rights', function () {
    // Guard for routes to come: a new legacy route without ->can() fails here.
    // Only the customer list and the own customer need no right - a
    // customer-bound token sees itself there, nothing else.
    $open = ['GET api/customers', 'GET api/{customer}'];

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $values = [
        '{customer}' => $customer->slug,
        '{site}' => $site->id,
        '{accesspoint}' => Accesspoint::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id])->id,
        '{server}' => rackTestServer($customer, $site)->id,
    ];
    $token = legacyToken([]);

    $routes = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
        $action = $route->getActionName();

        return str_starts_with($route->uri(), 'api/')
            && str_starts_with($action, 'App\\Http\\Controllers\\API\\')
            && ! str_contains($action, '\\Mobile\\')
            && ! str_contains($action, 'AgentController')
            && ! str_contains($action, 'ChecklistReportController');
    });
    expect($routes)->not->toBeEmpty();

    $unprotected = [];
    foreach ($routes as $route) {
        $method = $route->methods()[0];
        $key = $method.' '.$route->uri();

        if (in_array($key, $open, true)) {
            continue;
        }

        $status = legacyRequest($token, $method, '/'.strtr($route->uri(), $values))->status();
        if ($status !== 403) {
            $unprotected[] = "$key ($status)";
        }
    }

    expect($unprotected)->toBe([], 'Legacy routes without a right check: '.implode(', ', $unprotected));
});
