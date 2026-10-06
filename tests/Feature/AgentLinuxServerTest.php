<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;
use App\Models\VM;

/*
 * The Linux agent (Debian/Ubuntu): hardware becomes a server, a VM completes
 * the entry its hypervisor's agent already made.
 */

function linuxToken(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site, AgentToken::generateFor($customer, $site, 'Linux', now()->addMonth())[1]];
}

function linuxMeldung(array $anders = []): array
{
    return ['server' => array_merge([
        'identifier' => 'linux/0f1e2d3c',
        'hostname' => 'srv-web01.firma.local',
        'manufacturer' => 'Dell Inc.',
        'model' => 'PowerEdge R250',
        'serial' => 'ABC1234',
        'ip' => '192.168.10.21',
        'os_id' => 'debian',
        'os_version' => '12',
        'virtual' => false,
        'cpu' => 'Intel Xeon E-2314 (4 Kerne)',
        'memory_gb' => 32,
        'services' => ['nginx', 'mariadb', 'docker', 'sshd'],
    ], $anders)];
}

test('a physical Linux server is documented as server with OS and services', function () {
    [$customer, , $plain] = linuxToken();
    $debian = OperatingSystem::factory()->create(['name' => 'Debian 12']);
    foreach (['mariadb', 'docker'] as $name) {
        Service::firstOrCreate(['name' => $name]);
    }

    $this->withToken($plain)->postJson('/api/agent/linux-server', linuxMeldung())
        ->assertOk()
        ->assertJsonPath('type', 'server');

    $server = Server::where('customer_id', $customer->id)->sole();
    expect($server->name)->toBe('srv-web01.firma.local')
        ->and($server->operating_system_id)->toBe($debian->id)
        ->and($server->getRawOriginal('services'))->toBe('mariadb,docker');
});

test('a VM completes the VM its hypervisor documented instead of becoming a server', function () {
    [$customer, $site, $plain] = linuxToken();
    $debian = OperatingSystem::factory()->create(['name' => 'Debian 12']);
    $vm = VM::create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SRV-WEB01', 'agent_identifier' => 'pve01/qemu/101']);

    $this->withToken($plain)->postJson('/api/agent/linux-server', linuxMeldung(['virtual' => true]))
        ->assertOk()
        ->assertJsonPath('type', 'vm');

    expect(Server::where('customer_id', $customer->id)->count())->toBe(0);
    $vm->refresh();
    expect($vm->operating_system_id)->toBe($debian->id)
        // The hypervisor's agent keeps its identifier.
        ->and($vm->agent_identifier)->toBe('pve01/qemu/101');
});

test('a VM nobody documented yet is created as VM', function () {
    [$customer, , $plain] = linuxToken();

    $this->withToken($plain)->postJson('/api/agent/linux-server', linuxMeldung(['virtual' => true]))->assertOk();
    $this->withToken($plain)->postJson('/api/agent/linux-server', linuxMeldung(['virtual' => true]))->assertOk();

    expect(VM::where('customer_id', $customer->id)->sole()->agent_identifier)->toBe('linux/0f1e2d3c');
});

test('services maintained by hand are kept', function () {
    [$customer, $site, $plain] = linuxToken();
    Service::firstOrCreate(['name' => 'docker']);
    $this->withToken($plain)->postJson('/api/agent/linux-server', linuxMeldung());
    Server::where('customer_id', $customer->id)->sole()->update(['services' => 'Fileserver']);

    $this->withToken($plain)->postJson('/api/agent/linux-server', linuxMeldung());

    expect(Server::where('customer_id', $customer->id)->sole()->getRawOriginal('services'))->toBe('Fileserver');
});

test('the Linux installer is the shared one with KIND linux', function () {
    $this->actingAs(userWithPermissions(['agent_manage']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->post(route('agent.store', $customer), ['name' => 'Linux', 'site_id' => $site->id, 'expires_at' => now()->addMonth()->format('Y-m-d')]);
    $plain = session('newToken');

    $antwort = $this->get(route('agent.dienst.linux', $customer))->assertOk();

    expect($antwort->getContent())->toContain('KIND="linux"')->toContain('TOKEN="'.$plain.'"');
    expect($antwort->headers->get('Content-Disposition'))->toContain('dokuvault-agent-linux.sh');
});

test('the Linux agent checks in with its role and gets the bash script', function () {
    [, , $plain] = linuxToken();

    $this->withToken($plain)->postJson('/api/agent/checkin', [
        'kind' => 'linux', 'machine_id' => '0f1e2d3c', 'hostname' => 'srv-web01', 'detected' => ['linux-server'],
    ])->assertJsonPath('roles', ['linux-server']);

    $this->withToken($plain)->get('/api/agent/script/linux-server?shell=bash')
        ->assertOk()
        ->assertSee('/api/agent/linux-server', false);
});
