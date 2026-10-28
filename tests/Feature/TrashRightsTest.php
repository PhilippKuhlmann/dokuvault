<?php

use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\Site;

// Security audit hardening note: the customer trash was gated by see_hidden
// only - restoring needed no right on the kind of object, and the list showed
// names of every kind, also those the user may not see.

function trashedSite(Customer $customer, string $name = 'Alter Standort'): Site
{
    $site = Site::factory()->create(['customer_id' => $customer->id, 'name' => $name]);
    $site->delete();

    return $site;
}

test('restoring needs the delete right of that kind', function () {
    $customer = Customer::factory()->create();
    $site = trashedSite($customer);
    $this->actingAs(userWithPermissions(['see_hidden', 'site_viewAny']));

    $this->post(route('trash.restore', [$customer, 'site', $site->id]))->assertForbidden();

    expect($site->fresh()->trashed())->toBeTrue();
});

test('with the delete right restoring works as before', function () {
    $customer = Customer::factory()->create();
    $site = trashedSite($customer);
    $this->actingAs(userWithPermissions(['see_hidden', 'site_viewAny', 'site_delete']));

    $this->post(route('trash.restore', [$customer, 'site', $site->id]))->assertRedirect();

    expect($site->fresh()->trashed())->toBeFalse();
});

test('the trash lists only kinds the user may see', function () {
    $customer = Customer::factory()->create();
    trashedSite($customer, 'Sichtbarer Standort');
    // The trash names a login by its username.
    $login = LoginGeneral::create(['customer_id' => $customer->id, 'description' => 'Login', 'username' => 'geheimer.dienstzugang', 'password' => 'y']);
    $login->delete();
    $this->actingAs(userWithPermissions(['see_hidden', 'site_viewAny']));

    $this->get(route('trash.index', $customer))
        ->assertOk()
        ->assertSee('Sichtbarer Standort')
        ->assertDontSee('geheimer.dienstzugang');
});
