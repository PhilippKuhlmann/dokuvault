<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\RackItem;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;

/*
 * "In VM umwandeln": a server that is in truth a VM becomes one - new, or
 * merged into a VM a hypervisor agent already documents.
 */

function umwandelnFixture(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $server = Server::forceCreate([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-FALSCH',
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022 Standard'])->id,
        'services' => 'AD,DNS', 'agent_identifier' => 'machine-guid-1',
    ]);
    $server->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.0.0.50']);

    return [$customer, $site, $server];
}

// One user per test: switching mid-test ends the session (AuthenticateSession).
test('the server card offers the conversion with the right to delete servers', function () {
    [$customer, , $server] = umwandelnFixture();
    $this->actingAs(userWithPermissions(['server_viewAny', 'server_delete']));

    $this->get(route('server.index', $customer))->assertOk()
        ->assertSee(route('server.to-vm', [$customer, $server]), false);
});

test('without the right to delete servers the card offers no conversion', function () {
    [$customer, , $server] = umwandelnFixture();
    $this->actingAs(userWithPermissions(['server_viewAny', 'server_update']));

    $this->get(route('server.index', $customer))->assertOk()
        ->assertDontSee(route('server.to-vm', [$customer, $server]), false);
});

test('a server becomes a new VM with what hangs on it', function () {
    [$customer, $site, $server] = umwandelnFixture();
    $host = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-01']);
    $this->actingAs(userWithPermissions(['server_delete', 'vm_create', 'vm_viewAny']));

    $this->get(route('server.to-vm', [$customer, $server]))->assertOk()
        ->assertSee('Als neue VM anlegen')
        ->assertSee('PVE-01');

    $antwort = $this->post(route('server.to-vm.store', [$customer, $server]), [
        'mode' => 'new', 'name' => 'VM-RICHTIG', 'server_id' => $host->id,
    ]);

    $vm = VM::where('customer_id', $customer->id)->sole();
    $antwort->assertRedirect(route('vm.index', [$customer, 'highlight' => $vm->id]))
        ->assertSessionHas('success');

    expect($vm->name)->toBe('VM-RICHTIG')
        ->and($vm->host->id)->toBe($host->id)
        ->and($vm->operatingSystem->name)->toBe('Windows Server 2022 Standard')
        ->and($vm->getRawOriginal('services'))->toBe('AD,DNS')
        ->and($vm->ipAddresses()->pluck('address')->all())->toBe(['10.0.0.50'])
        // So the Windows agent finds its machine on the next run.
        ->and($vm->guest_identifier)->toBe('machine-guid-1')
        ->and($server->fresh()->trashed())->toBeTrue();
});

test('a server is merged into an existing VM, the VM keeps its name', function () {
    [$customer, $site, $server] = umwandelnFixture();
    $vm = VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'test', 'agent_identifier' => 'srv-pve02/qemu/101']);
    $vm->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.0.0.50']);
    $this->actingAs(userWithPermissions(['server_delete', 'vm_update', 'vm_viewAny']));

    $this->post(route('server.to-vm.store', [$customer, $server]), ['mode' => 'merge', 'vm_id' => $vm->id])
        ->assertRedirect(route('vm.index', [$customer, 'highlight' => $vm->id]));

    $vm->refresh();
    expect($vm->name)->toBe('test')
        ->and($vm->agent_identifier)->toBe('srv-pve02/qemu/101')
        // The same address once, not twice.
        ->and($vm->ipAddresses()->count())->toBe(1)
        ->and($vm->getRawOriginal('services'))->toBe('AD,DNS')
        ->and($server->fresh()->trashed())->toBeTrue();
});

test('the Windows agent finds a converted machine on its next run instead of a new server', function () {
    [$customer, $site, $server] = umwandelnFixture();
    [, $plain] = AgentToken::generateFor($customer, $site, 'Agent');
    $this->actingAs(userWithPermissions(['server_delete', 'vm_create']));
    $this->post(route('server.to-vm.store', [$customer, $server]), ['mode' => 'new', 'name' => 'VM-RICHTIG']);

    $this->withToken($plain)->postJson('/api/agent/windows-server', ['server' => [
        'identifier' => 'machine-guid-1', 'hostname' => 'SRV-FALSCH', 'ip' => '10.0.0.50',
    ]])->assertOk()->assertJson(['type' => 'vm', 'vm' => 'VM-RICHTIG']);

    expect(Server::where('customer_id', $customer->id)->count())->toBe(0)
        ->and(VM::where('customer_id', $customer->id)->sole()->hostname)->toBe('SRV-FALSCH');
});

test('a server in a rack or with guests cannot be converted', function () {
    [$customer, $site, $server] = umwandelnFixture();
    $rack = Rack::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'R1', 'height_units' => 42]);
    RackItem::create(['rack_id' => $rack->id, 'position' => 1, 'height_units' => 1, 'device_type' => Server::class, 'device_id' => $server->id]);
    $this->actingAs(userWithPermissions(['server_delete', 'vm_create']));

    $this->get(route('server.to-vm', [$customer, $server]))->assertOk()
        ->assertSee('Serverschrank eingebaut')
        ->assertDontSee('name="mode"', false);

    $this->post(route('server.to-vm.store', [$customer, $server]), ['mode' => 'new', 'name' => 'X'])
        ->assertSessionHasErrors('server');
    expect($server->fresh()->trashed())->toBeFalse();

    $host = Server::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'HOST']);
    VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'GAST', 'server_id' => $host->id]);
    $this->get(route('server.to-vm', [$customer, $host]))->assertSee('Ein Host bleibt ein Server.');
});

test('without the right to delete servers the page is closed', function () {
    [$customer, , $server] = umwandelnFixture();
    $this->actingAs(userWithPermissions(['vm_create']));

    $this->get(route('server.to-vm', [$customer, $server]))->assertForbidden();
});

test('without the right to create VMs no new VM is made', function () {
    [$customer, , $server] = umwandelnFixture();
    $this->actingAs(userWithPermissions(['server_delete']));

    $this->post(route('server.to-vm.store', [$customer, $server]), ['mode' => 'new', 'name' => 'X'])->assertForbidden();

    expect($server->fresh()->trashed())->toBeFalse()
        ->and(VM::count())->toBe(0);
});

test('another customer\'s server or VM is out of reach', function () {
    [$customer, , $server] = umwandelnFixture();
    [$fremd, $fremdSite, $fremderServer] = umwandelnFixture();
    $fremdeVm = VM::forceCreate(['customer_id' => $fremd->id, 'site_id' => $fremdSite->id, 'name' => 'FREMD']);
    $this->actingAs(userWithPermissions(['server_delete', 'vm_create', 'vm_update']));

    // The server of another customer under this customer's address.
    $this->get(route('server.to-vm', [$customer, $fremderServer]))->assertNotFound();

    $this->post(route('server.to-vm.store', [$customer, $server]), ['mode' => 'merge', 'vm_id' => $fremdeVm->id])
        ->assertSessionHasErrors('vm_id');
    expect($server->fresh()->trashed())->toBeFalse();
});
