<?php

use App\Livewire\CopyPasswordButton;
use App\Models\CredentialLink;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\Rack;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
 * The head line of a card after design 1A: fixed columns with a dash for a
 * missing value, the place in short, "copy password" next to the pencil,
 * and the foot of an opened card.
 */

function headServer(array $attributes = []): Server
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Zentrale Hamburg']);

    return Server::forceCreate(array_merge([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-DC01', 'form_factor' => 'tower',
    ], $attributes));
}

function headLink(Server $server, string $name, string $password, ?string $note = null): CredentialLink
{
    $login = LoginGeneral::create(['customer_id' => $server->customer_id, 'name' => $name, 'username' => 'admin', 'password' => $password]);
    $link = new CredentialLink(['note' => $note, 'customer_id' => $server->customer_id]);
    $link->login_general_id = $login->id;
    $link->credentialable()->associate($server);
    $link->save();

    return $link;
}

test('one linked password: one button copies it, logged like the field in the card', function () {
    $this->actingAs(userWithPermissions(['server_viewAny', 'logingeneral_viewAny']));
    $server = headServer();
    $link = headLink($server, 'Domänen-Admin', 'Kopf-Kennwort-2026');

    Livewire::test(CopyPasswordButton::class, ['device' => $server])
        ->assertSee('Kennwort kopieren: Domänen-Admin')
        // The value is not in the page before the click ...
        ->assertDontSee('Kopf-Kennwort-2026')
        ->call('copy', $link->id)
        // ... and goes to the browser once, as an event.
        ->assertDispatched('password-ready', wert: 'Kopf-Kennwort-2026');

    expect(Activity::where('event', 'kennwort_angesehen')->count())->toBe(1);
});

test('several passwords: a menu names them', function () {
    $this->actingAs(userWithPermissions(['server_viewAny', 'logingeneral_viewAny']));
    $server = headServer();
    headLink($server, 'Domänen-Admin', 'a');
    headLink($server, 'iDRAC', 'b', 'BMC root');

    Livewire::test(CopyPasswordButton::class, ['device' => $server])
        ->assertSee('<details', false)
        ->assertSee('Domänen-Admin')
        ->assertSee('BMC root');
});

test('a link of another device is refused, and without the right there is no button', function () {
    $this->actingAs(userWithPermissions(['server_viewAny', 'logingeneral_viewAny']));
    $server = headServer();
    headLink($server, 'Domänen-Admin', 'eigen');
    $fremd = headLink(headServer(['name' => 'SRV-FREMD']), 'Fremd', 'fremd-2026');

    Livewire::test(CopyPasswordButton::class, ['device' => $server])
        ->call('copy', $fremd->id)
        ->assertNotDispatched('password-ready');

    $this->actingAs(userWithPermissions(['server_viewAny']));
    Livewire::test(CopyPasswordButton::class, ['device' => $server])
        ->assertSet('links', [])
        ->assertDontSee('Kennwort kopieren');
});

test('the place is the rack in short, else the site; a missing IP shows a dash', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));
    $tower = headServer();
    $rackServer = Server::forceCreate([
        'customer_id' => $tower->customer_id, 'site_id' => $tower->site_id, 'name' => 'PVE-01', 'form_factor' => 'rack', 'height_units' => 2,
    ]);
    $rack = Rack::create(['customer_id' => $tower->customer_id, 'site_id' => $tower->site_id, 'name' => 'Rack HH-01', 'height_units' => 42]);
    $rack->items()->create(['device_type' => Server::class, 'device_id' => $rackServer->id, 'position' => 8, 'height_units' => 2, 'side' => 'front']);

    expect($rackServer->fresh()->mountLocationShort())->toBe('Rack HH-01 · HE 8–9')
        ->and($tower->mountLocationShort())->toBeNull();

    $this->get(route('server.index', $tower->customer))->assertOk()
        ->assertSee('Rack HH-01 · HE 8–9')
        ->assertSee('title="Rack HH-01 · HE 8–9 · Vorderseite"', false)
        ->assertSee('Zentrale Hamburg')
        ->assertSee('—');
});

test('the foot of an opened card: agent, last change, and the links one may follow', function () {
    $this->actingAs(userWithPermissions(['server_viewAny', 'rack_viewAny', 'admin_activity']));
    $server = headServer(['agent_reported_at' => now()->subMinutes(4)]);
    $rack = Rack::create(['customer_id' => $server->customer_id, 'site_id' => $server->site_id, 'name' => 'Rack HH-01', 'height_units' => 42]);
    $rack->items()->create(['device_type' => Server::class, 'device_id' => $server->id, 'position' => 4, 'height_units' => 1, 'side' => 'front']);
    $server->update(['model' => 'PowerEdge R650']);

    $html = Blade::render('<x-kartenfuss :eintrag="$e" />', ['e' => $server->fresh()]);

    // The agent time stands here, and only here - not in the agent block.
    expect($html)->toContain('Agent: vor 4 Minuten')
        ->and($html)->toContain('Geändert am')
        ->and($html)->toContain('Verlauf')
        ->and($html)->toContain('Im Serverplan zeigen')
        ->and($html)->toContain('In Rack HH-01 zeigen');

    // Without the rights the links stay away.
    $this->actingAs(userWithPermissions(['server_viewAny']));
    $html = Blade::render('<x-kartenfuss :eintrag="$e" />', ['e' => $server->fresh()]);
    expect($html)->not->toContain('Verlauf')->and($html)->not->toContain('In Rack HH-01 zeigen');
});
