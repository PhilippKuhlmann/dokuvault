<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Site;
use App\Models\VM;

/*
 * VM or container (LXC) - a field of the guest, set by hand or by the
 * Proxmox agent. Before, only the agent's report knew it.
 */

test('the VM form saves whether a guest is a VM or a container', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Debian 13']);
    $this->actingAs(userWithPermissions(['vm_viewAny', 'vm_create']));

    imModal('vm', $customer, ['site_id' => $site->id, 'name' => 'CT-DNS', 'kind' => 'lxc', 'operating_system_id' => $os->id])
        ->assertHasNoErrors();
    expect(VM::where('name', 'CT-DNS')->sole()->kind)->toBe('lxc');

    imModal('vm', $customer, ['site_id' => $site->id, 'name' => 'X', 'kind' => 'docker', 'operating_system_id' => $os->id])
        ->assertHasErrors('form.kind');
});

test('a new VM is a VM unless said otherwise', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $vm = VM::forceCreate(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'VM-NEU']);

    expect($vm->fresh()->kind)->toBe('vm')
        ->and($vm->fresh()->istContainer())->toBeFalse();
});

test('the Proxmox agent sets the kind from what it reports', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site, 'PVE');

    $this->withToken($plain)->postJson('/api/agent/proxmox', [
        'host' => ['identifier' => 'pve', 'hostname' => 'pve'],
        'guests' => [
            ['identifier' => 'pve/qemu/100', 'name' => 'web01', 'type' => 'qemu'],
            ['identifier' => 'pve/lxc/200', 'name' => 'ct-dns', 'type' => 'lxc'],
        ],
    ])->assertOk();

    expect(VM::where('customer_id', $customer->id)->orderBy('name')->pluck('kind', 'name')->all())
        ->toBe(['ct-dns' => 'lxc', 'web01' => 'vm']);
});
