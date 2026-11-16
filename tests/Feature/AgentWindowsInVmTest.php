<?php

use App\Models\ADDomain;
use App\Models\ADDomainHost;
use App\Models\AgentToken;
use App\Models\CredentialLink;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Models\NetworkShare;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\RackItem;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;
use App\Models\VM;

/*
 * Windows running in a VM: the Proxmox agent reports the VM with its BIOS
 * UUID, the Windows agent inside reads the same UUID - its report lands on
 * the VM instead of documenting the machine a second time as a server.
 */

const VM_UUID = '1eccdb20-8ac0-4a86-b80d-3f7c188feb66';

function windowsVmKunde(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site, 'Agent');

    return [$customer, $site, $plain];
}

function proxmoxMitWindowsGast(string $plain, ?string $uuid = VM_UUID)
{
    return test()->withToken($plain)->postJson('/api/agent/proxmox', [
        'host' => ['identifier' => 'pve02-id', 'hostname' => 'srv-pve02', 'memory_gb' => 32],
        'guests' => [[
            'identifier' => 'srv-pve02/qemu/101', 'vmid' => 101, 'name' => 'test', 'type' => 'qemu',
            'status' => 'running', 'cores' => 4, 'memory_gb' => 4, 'smbios_uuid' => $uuid,
        ]],
    ]);
}

function windowsInDerVm(string $plain, array $overrides = [])
{
    return test()->withToken($plain)->postJson('/api/agent/windows-server', ['server' => $overrides + [
        'identifier' => 'fc7056a0-machine-guid',
        'hostname' => 'WIN-H4KK383P230',
        'manufacturer' => 'QEMU',
        'model' => 'Standard PC (Q35 + ICH9, 2009)',
        'os' => 'Microsoft Windows Server 2025 Standard',
        'ip' => '10.10.250.166',
        'cpu' => 'QEMU Virtual CPU version 2.5+ (4 Kerne)',
        'memory_gb' => 4,
        'roles' => ['AD-Domain-Services', 'DNS'],
        // Windows reports it in capitals.
        'smbios_uuid' => strtoupper(VM_UUID),
    ]]);
}

test('the Proxmox agent stores the BIOS UUID of a VM, placeholders not', function () {
    [$customer, , $plain] = windowsVmKunde();

    proxmoxMitWindowsGast($plain, '{1ECCDB20-8AC0-4A86-B80D-3F7C188FEB66}')->assertOk();
    expect(VM::where('customer_id', $customer->id)->sole()->smbios_uuid)->toBe(VM_UUID);

    [$anderer, , $plain2] = windowsVmKunde();
    proxmoxMitWindowsGast($plain2, '00000000-0000-0000-0000-000000000000')->assertOk();
    expect(VM::where('customer_id', $anderer->id)->sole()->smbios_uuid)->toBeNull();
});

test('Windows in a known VM lands on that VM, not on a server', function () {
    [$customer, , $plain] = windowsVmKunde();
    OperatingSystem::firstOrCreate(['name' => 'Windows Server 2025 Standard']);
    Service::firstOrCreate(['name' => 'AD']);
    Service::firstOrCreate(['name' => 'DNS']);
    proxmoxMitWindowsGast($plain)->assertOk();

    windowsInDerVm($plain)->assertOk()
        ->assertJson(['type' => 'vm', 'vm' => 'test']);

    $vm = VM::where('customer_id', $customer->id)->sole();
    expect(Server::where('customer_id', $customer->id)->where('name', 'WIN-H4KK383P230')->exists())->toBeFalse()
        // Name and figures stay those of the hypervisor.
        ->and($vm->name)->toBe('test')
        ->and($vm->agent_details['memory_gb'])->toBe(4)
        ->and($vm->hostname)->toBe('WIN-H4KK383P230')
        ->and($vm->operatingSystem->name)->toBe('Windows Server 2025 Standard')
        ->and($vm->ipAddresses()->pluck('address')->all())->toBe(['10.10.250.166'])
        ->and($vm->getRawOriginal('services'))->toBe('AD,DNS');

    // A second run changes nothing about that.
    windowsInDerVm($plain)->assertOk();
    expect(Server::where('customer_id', $customer->id)->where('name', 'WIN-H4KK383P230')->exists())->toBeFalse()
        ->and($vm->ipAddresses()->count())->toBe(1);
});

test('the byte-swapped spelling of the UUID matches as well', function () {
    [, , $plain] = windowsVmKunde();
    proxmoxMitWindowsGast($plain)->assertOk();

    // First three fields turned round: 20dbcc1e-c08a-864a-...
    windowsInDerVm($plain, ['smbios_uuid' => '20dbcc1e-c08a-864a-b80d-3f7c188feb66'])
        ->assertJson(['type' => 'vm']);
});

test('without a VM of that UUID it stays a server as before', function () {
    [$customer, , $plain] = windowsVmKunde();

    windowsInDerVm($plain)->assertOk()->assertJson(['server' => 'WIN-H4KK383P230']);

    expect(Server::where('customer_id', $customer->id)->where('agent_identifier', 'fc7056a0-machine-guid')->exists())->toBeTrue();
});

test('the server of an earlier run moves into the VM with what hangs on it', function () {
    [$customer, , $plain] = windowsVmKunde();

    // Before the Proxmox agent knew the UUID: a server of its own.
    windowsInDerVm($plain)->assertOk();
    $server = Server::where('customer_id', $customer->id)->where('agent_identifier', 'fc7056a0-machine-guid')->sole();
    $server->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.10.250.200']);
    $server->update(['services' => 'FS']);
    $login = LoginGeneral::factory()->create(['customer_id' => $customer->id]);
    CredentialLink::create(['customer_id' => $customer->id, 'login_general_id' => $login->id, 'credentialable_type' => Server::class, 'credentialable_id' => $server->id]);
    $domaene = ADDomain::factory()->create(['customer_id' => $customer->id]);
    ADDomainHost::create(['ad_domain_id' => $domaene->id, 'role' => 'dc', 'host_type' => Server::class, 'host_id' => $server->id]);
    $freigabe = NetworkShare::create(['customer_id' => $customer->id, 'name' => 'Daten', 'path' => '\\\\WIN-H4KK383P230\\Daten', 'host_type' => Server::class, 'host_id' => $server->id]);

    proxmoxMitWindowsGast($plain)->assertOk();
    windowsInDerVm($plain)->assertOk()->assertJson(['type' => 'vm', 'servers_folded' => 1]);

    $vm = VM::where('customer_id', $customer->id)->sole();
    expect($server->fresh()->trashed())->toBeTrue()
        ->and($vm->ipAddresses()->pluck('address')->sort()->values()->all())->toBe(['10.10.250.166', '10.10.250.200'])
        ->and($server->ipAddresses()->count())->toBe(0)
        ->and($vm->credentialLinks()->pluck('login_general_id')->all())->toBe([$login->id])
        ->and(ADDomainHost::sole()->host_type)->toBe(VM::class)
        ->and($domaene->fresh()->hostNames('dc'))->toBe('test')
        ->and($freigabe->fresh()->host_id)->toBe($vm->id)
        ->and($freigabe->fresh()->host_type)->toBe(VM::class)
        ->and($vm->fresh()->getRawOriginal('services'))->toBe('FS');
});

test('a server in a rack stays for a human to sort out', function () {
    [$customer, $site, $plain] = windowsVmKunde();
    windowsInDerVm($plain)->assertOk();
    $server = Server::where('customer_id', $customer->id)->where('agent_identifier', 'fc7056a0-machine-guid')->sole();
    $rack = Rack::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'R1', 'height_units' => 42]);
    RackItem::create(['rack_id' => $rack->id, 'position' => 1, 'height_units' => 1, 'device_type' => Server::class, 'device_id' => $server->id]);

    proxmoxMitWindowsGast($plain)->assertOk();
    windowsInDerVm($plain)->assertOk()->assertJson(['type' => 'vm', 'servers_folded' => 0]);

    expect($server->fresh()->trashed())->toBeFalse();
});

test('the AD agent finds a domain controller by the host name Windows reports in the VM', function () {
    [$customer, , $plain] = windowsVmKunde();
    proxmoxMitWindowsGast($plain)->assertOk();
    windowsInDerVm($plain)->assertOk();

    $this->withToken($plain)->postJson('/api/agent/windows-ad', [
        'domain' => 'kuhlmann.local',
        'users' => [],
        'groups' => [],
        'ad' => ['domain_controllers' => [['name' => 'win-h4kk383p230.kuhlmann.local']]],
    ])->assertOk()->assertJson(['unmatched_hosts' => []]);

    $domaene = ADDomain::where('customer_id', $customer->id)->sole();
    expect($domaene->hostNames('dc'))->toBe('test');
});

test('the VM card shows the host name when it differs from the name', function () {
    [$customer, , $plain] = windowsVmKunde();
    proxmoxMitWindowsGast($plain)->assertOk();
    windowsInDerVm($plain)->assertOk();
    $this->actingAs(userWithPermissions(['vm_viewAny']));

    $this->get(route('vm.index', $customer))->assertOk()
        ->assertSeeInOrder(['Hostname', 'WIN-H4KK383P230']);
});
