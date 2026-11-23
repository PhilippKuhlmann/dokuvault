<?php

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
 * Not listed: admin/* (cross-customer settings; the customer pages there
 * check CustomerAccess themselves), the agent API (token, no user) and
 * Livewire (components announce their customer, ScopedToCustomer).
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
];

test('every writing route without {customer} has been checked for the team levels', function () {
    $found = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($route) => collect($route->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->map(fn ($method) => $method.' '.$route->uri()))
        ->reject(fn ($entry) => str_contains($entry, '{customer}')
            || preg_match('#^\w+ (admin|api/agent|livewire|_ignition|telescope|_boost)#', $entry))
        ->unique()->sort()->values();

    expect($found->diff(REVIEWED_WRITE_ROUTES)->values()->all())->toBe([]);
});
