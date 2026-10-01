<?php

use App\Livewire\ObjektFormular;
use App\Models\ADDomain;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;
use Livewire\Livewire;

function adHost(Customer $customer, string $klasse, string $name)
{
    $site = Site::firstOrCreate(
        ['customer_id' => $customer->id, 'name' => 'Zentrale'],
        Site::factory()->make(['customer_id' => $customer->id])->toArray(),
    );

    // OS explicitly: the factories otherwise roll an id that need not exist.
    return $klasse::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Windows Server 2022'])->id,
        'name' => $name,
    ]);
}

test('the extended domain details are saved through the modal', function () {
    $customer = Customer::factory()->create();
    $dc1 = adHost($customer, Server::class, 'SRV-DC01');
    $dc2 = adHost($customer, VM::class, 'VM-DC02');
    $sync = adHost($customer, VM::class, 'VM-SYNC01');
    $this->actingAs(userWithPermissions(['addomain_create']));

    imModal('addomain', $customer, [
        'domain' => 'firma.local',
        'netbios' => 'FIRMA',
        'dsrmpassword' => 'Geheim123!',
        'functional_level' => '2016',
        'domain_controllers' => ['server:'.$dc1->id, 'vm:'.$dc2->id],
        'fsmo_holder' => 'SRV-DC01',
        'entra_connect' => '1',
        'entra_connect_server' => 'vm:'.$sync->id,
        'certificate_authority' => 'server:'.$dc1->id,
    ])->assertHasNoErrors();

    $domaene = ADDomain::where('domain', 'firma.local')->first();
    expect($domaene->customer_id)->toBe($customer->id);
    expect($domaene->hostNames('dc'))->toBe('SRV-DC01, VM-DC02');
    expect($domaene->hostNames('entra_connect'))->toBe('VM-SYNC01');
    expect($domaene->hostNames('ca'))->toBe('SRV-DC01');
    expect((bool) $domaene->entra_connect)->toBeTrue();
    expect($domaene->functionalLevelLabel())->toBe('Windows Server 2016');
});

test('editing loads the selected machines and replaces them on save', function () {
    $customer = Customer::factory()->create();
    $alt = adHost($customer, Server::class, 'SRV-DC01');
    $neu = adHost($customer, VM::class, 'VM-DC03');
    $domaene = ADDomain::factory()->create(['customer_id' => $customer->id]);
    $domaene->syncHosts('dc', ['server:'.$alt->id]);
    $this->actingAs(userWithPermissions(['addomain_update']));

    Livewire::test(ObjektFormular::class, ['typ' => 'addomain', 'customer' => $customer])
        ->call('bearbeiten', 'addomain', $domaene->id)
        ->assertSet('form.domain_controllers', ['server:'.$alt->id])
        ->set('form.domain_controllers', ['vm:'.$neu->id])
        ->call('speichern')
        ->assertHasNoErrors();

    expect($domaene->fresh()->hostNames('dc'))->toBe('VM-DC03');
});

test('a machine of another customer cannot be selected', function () {
    $customer = Customer::factory()->create();
    $fremd = adHost(Customer::factory()->create(), Server::class, 'FREMD-DC');
    $this->actingAs(userWithPermissions(['addomain_create']));

    // The keys come from the browser - a forged id must not link a foreign server.
    imModal('addomain', $customer, [
        'domain' => 'fremd.local', 'netbios' => 'FREMD', 'dsrmpassword' => 'x',
        'domain_controllers' => ['server:'.$fremd->id],
    ])->assertHasErrors('form.domain_controllers.0');

    expect(ADDomain::where('domain', 'fremd.local')->exists())->toBeFalse();
});

test('a new domain claims neither a functional level nor Entra Connect', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['addomain_create']));

    // Select fields preselect their first entry - that must be "unknown".
    imModal('addomain', $customer, [
        'domain' => 'leer.local', 'netbios' => 'LEER', 'dsrmpassword' => 'x',
    ])->assertHasNoErrors();

    $domaene = ADDomain::where('domain', 'leer.local')->first();
    expect($domaene->functional_level)->toBeNull();
    expect($domaene->entra_connect)->toBeNull();
    expect($domaene->hosts()->count())->toBe(0);
});

test('an unknown functional level is rejected', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['addomain_create']));

    imModal('addomain', $customer, [
        'domain' => 'krumm.local', 'netbios' => 'KRUMM', 'dsrmpassword' => 'x',
        'functional_level' => '2019',
    ])->assertHasErrors('form.functional_level');
});
