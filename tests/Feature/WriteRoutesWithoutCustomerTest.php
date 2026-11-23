<?php

use App\Livewire\Concerns\ScopedToCustomer;
use Illuminate\Support\Facades\Route;

/*
 * A watch over the routes that write without a {customer} in their address.
 *
 * The team levels (CustomerAccess) learn the customer from the route - or
 * the route has to announce it itself, or every write it does has to pass
 * the model guard. Four such routes slipped through a review once (mobile
 * objects, the trash, the API's new customer). A new one shall not slip in
 * unseen: it lands here, someone checks how it learns its customer, and
 * adds it below with the reason.
 *
 * Not listed: the agent API (token, no user) and Livewire - its
 * components announce their customer (ScopedToCustomer), watched by the
 * second test below. The admin area is listed: a login of a customer
 * handed out there was the way around "read" once (UserController).
 */
const REVIEWED_WRITE_ROUTES = [
    // Mobile objects: MobileObjects::finden() announces the object's customer;
    // IpAssignment, CredentialLinking, ScanTargetLinking and ObjectForm check
    // assertWritable before they write.
    'DELETE api/mobile/objects/scanner/{id}/scan-targets/{ziel}',
    'DELETE api/mobile/objects/{typ}/{id}',
    'DELETE api/mobile/objects/{typ}/{id}/credentials/{link}',
    'DELETE api/mobile/objects/{typ}/{id}/ip-addresses/{ip}',
    'POST api/mobile/objects/scanner/{id}/scan-targets',
    'POST api/mobile/objects/{typ}/{id}/credentials',
    'POST api/mobile/objects/{typ}/{id}/ip-addresses',
    'PUT api/mobile/objects/{typ}/{id}',
    'PATCH api/mobile/objects/{typ}/{id}',
    'POST api/mobile/objects/{typ}/{id}',
    // Reading a secret: PasswordReveal checks visibility and level.
    'POST api/mobile/credentials/{link}/password',
    'POST api/mobile/objects/{typ}/{id}/secret/{feld}',
    // Public API: single-model writes, stopped by the guard in OwnedByCustomer.
    'POST api/servers',
    'PUT api/servers/{server}',
    'DELETE api/servers/{server}',
    // A new customer: teams and levels through CustomerTeams.
    'POST api/customers',
    // The checklist script reports with a token, not as a user.
    'POST api/checklist-report',
    // The own account, sign-in, setup - no customer data.
    'DELETE api/mobile/profile', 'DELETE api/mobile/profile/two-factor', 'DELETE profile', 'DELETE two-factor',
    'PATCH profile', 'PUT api/mobile/profile', 'PATCH api/mobile/profile', 'PUT api/mobile/profile/password', 'PUT password',
    'POST api/mobile/login', 'POST api/mobile/login/two-factor', 'POST api/mobile/logout',
    'POST api/mobile/profile/two-factor/begin', 'POST api/mobile/profile/two-factor/confirm', 'POST api/mobile/profile/two-factor/recovery-codes',
    'POST forgot-password', 'POST invitation', 'POST locale/{locale}', 'POST login', 'POST logout', 'POST reset-password', 'POST setup',
    'POST two-factor', 'POST two-factor-challenge', 'POST two-factor-challenge/cancel', 'POST two-factor/cancel',
    'POST two-factor/confirm', 'POST two-factor/recovery-codes',
    // Redirects for every method (Route::redirect): / to the login, the old
    // invitation address to the new one - they write nothing.
    'POST /', 'PUT /', 'PATCH /', 'DELETE /',
    'POST einladung/{token}', 'PUT einladung/{token}', 'PATCH einladung/{token}', 'DELETE einladung/{token}',

    // Admin - customers and their logins: CustomerAccess checked by hand
    // (Customer updating/deleting, CustomerTeams, UserController::onlyWritable).
    'POST admin/customer', 'PATCH admin/customer/{customer}',
    'POST admin/user', 'PATCH admin/user/{user}', 'DELETE admin/user/{user}',
    'POST admin/user/{user}/invite', 'DELETE admin/user/{user}/two-factor',
    // Teams: members only (TeamController::onlyMember).
    'POST admin/team', 'PATCH admin/team/{team}', 'DELETE admin/team/{team}',
    // Settings and catalogues of the installation - no customer's data.
    'POST admin/role', 'PATCH admin/role/{role}',
    'PATCH admin/setting', 'PATCH admin/mail', 'DELETE admin/mail/password', 'POST admin/mail/test',
    'PATCH admin/directory', 'DELETE admin/directory/password', 'POST admin/directory/test', 'POST admin/directory/test-user',
    'POST admin/directory/groups', 'DELETE admin/directory/groups/{directoryGroupRole}', 'POST admin/directory/groups/{directoryGroupRole}/{richtung}',
    'POST admin/devicemodel/create', 'PATCH admin/devicemodel/{devicemodel}', 'DELETE admin/devicemodel/{devicemodel}',
    'POST admin/mailboxprovider/create', 'PATCH admin/mailboxprovider/{mailboxprovider}',
    'POST admin/operatingsystem/create', 'PATCH admin/operatingsystem/{operatingSystem}',
    'POST admin/rackcatalogitem/create', 'PATCH admin/rackcatalogitem/{rackcatalogitem}', 'DELETE admin/rackcatalogitem/{rackcatalogitem}',
    'POST admin/service/create', 'PATCH admin/service/{service}', 'DELETE admin/service/{service}',
    // Old addresses, redirects for every method.
    'POST admin/papierkorb', 'PUT admin/papierkorb', 'PATCH admin/papierkorb', 'DELETE admin/papierkorb',
    'POST admin/protokoll-historie', 'PUT admin/protokoll-historie', 'PATCH admin/protokoll-historie', 'DELETE admin/protokoll-historie',
];

/*
 * Livewire components that hold a customer must announce it on every
 * request (ScopedToCustomer) - otherwise a gate asked in an action does not
 * know the level of the user's team there.
 */
const LIVEWIRE_WITH_CUSTOMER_REVIEWED = [
    // Cross-customer admin lists: no customer of their own; every write
    // checks CustomerAccess per row (AdminTrash::endgueltig).
    'AdminActivityLog', 'AdminBackups', 'AdminTrash', 'AdminDataGrowth',
];

test('every writing route without {customer} has been checked for the team levels', function () {
    $found = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($route) => collect($route->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->map(fn ($method) => $method.' '.$route->uri()))
        ->reject(fn ($entry) => str_contains($entry, '{customer}')
            || preg_match('#^\w+ (api/agent/|livewire(-[0-9a-f]+)?/|_ignition/|telescope/|_boost/)#', $entry))
        ->unique()->sort()->values();

    expect($found->diff(REVIEWED_WRITE_ROUTES)->values()->all())->toBe([]);
});

test('every Livewire component holding a customer announces it', function () {
    $holding = collect(glob(app_path('Livewire/*.php')))
        ->map(fn ($file) => basename($file, '.php'))
        ->filter(fn ($name) => collect(['customerId', 'customer_id', 'kundeId', 'customer'])
            ->contains(fn ($property) => property_exists('App\\Livewire\\'.$name, $property)));
    // The search must find them - an empty list would pass without a look.
    expect($holding->count())->toBeGreaterThan(10);

    $missing = collect(glob(app_path('Livewire/*.php')))
        ->map(fn ($file) => basename($file, '.php'))
        ->filter(function ($name) {
            $class = 'App\\Livewire\\'.$name;
            $holds = collect(['customerId', 'customer_id', 'kundeId', 'customer'])
                ->contains(fn ($property) => property_exists($class, $property));

            return $holds && ! in_array(ScopedToCustomer::class, class_uses_recursive($class), true);
        })
        ->reject(fn ($name) => in_array($name, LIVEWIRE_WITH_CUSTOMER_REVIEWED, true))
        ->values()->all();

    expect($missing)->toBe([]);
});
