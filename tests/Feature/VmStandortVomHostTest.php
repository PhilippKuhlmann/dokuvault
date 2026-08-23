<?php

use App\Livewire\ObjektFormular;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;
use Livewire\Livewire;

function vmUmgebung(): array
{
    $customer = Customer::factory()->create();
    $hamburg = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Zentrale Hamburg']);
    $muenchen = Site::factory()->create(['customer_id' => $customer->id, 'name' => 'Filiale München']);
    // Ausdruecklich, weil die ServerFactory sonst eine Id zwischen 1 und 10
    // wuerfelt, ohne dass es die Zeile geben muss.
    $os = OperatingSystem::factory()->create(['name' => 'Proxmox VE 9']);

    return [$customer, $hamburg, $muenchen, $os];
}

test('mit Host kommt der Standort vom Host, nicht aus dem Formular', function () {
    [$customer, $hamburg, $muenchen, $os] = vmUmgebung();
    $this->actingAs(userWithPermissions(['vm_create']));

    $host = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $hamburg->id,
        'operating_system_id' => $os->id, 'name' => 'pve01',
    ]);

    // Der Standort wird mitgeschickt, als kaeme er aus einem alten Formular -
    // und zwar der falsche. Der Host gewinnt.
    $this->post(route('vm.store', $customer), [
        'server_id' => $host->id,
        'site_id' => $muenchen->id,
        'name' => 'VM-DC02',
        'operating_system_id' => $os->id,
    ])->assertSessionHasNoErrors();

    expect(VM::where('name', 'VM-DC02')->first()->site_id)->toBe($hamburg->id);
});

test('ohne Host bleibt der Standort Pflicht', function () {
    [$customer, $hamburg, $muenchen, $os] = vmUmgebung();
    $this->actingAs(userWithPermissions(['vm_create']));

    // Ein vServer beim Anbieter hat keinen dokumentierten Host - dort ist der
    // Standort die einzige Ortsangabe.
    $this->post(route('vm.store', $customer), [
        'name' => 'VM-Cloud', 'operating_system_id' => $os->id,
    ])->assertSessionHasErrors('site_id');

    $this->post(route('vm.store', $customer), [
        'site_id' => $muenchen->id, 'name' => 'VM-Cloud', 'operating_system_id' => $os->id,
    ])->assertSessionHasNoErrors();

    expect(VM::where('name', 'VM-Cloud')->first()->site_id)->toBe($muenchen->id);
});

test('ein Hostwechsel zieht den Standort der VM mit', function () {
    [$customer, $hamburg, $muenchen, $os] = vmUmgebung();
    $this->actingAs(userWithPermissions(['vm_update']));

    $alterHost = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $hamburg->id,
        'operating_system_id' => $os->id, 'name' => 'pve01',
    ]);
    $neuerHost = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $muenchen->id,
        'operating_system_id' => $os->id, 'name' => 'pve02',
    ]);
    $vm = VM::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $hamburg->id,
        'server_id' => $alterHost->id, 'operating_system_id' => $os->id, 'name' => 'VM-Umzug',
    ]);

    $this->patch(route('vm.update', [$customer, $vm]), [
        'server_id' => $neuerHost->id, 'name' => 'VM-Umzug', 'operating_system_id' => $os->id,
    ])->assertSessionHasNoErrors();

    expect($vm->fresh()->site_id)->toBe($muenchen->id);
});

test('auch im Modal genuegt der Host allein', function () {
    [$customer, $hamburg, $muenchen, $os] = vmUmgebung();
    $this->actingAs(userWithPermissions(['vm_create']));

    $host = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $hamburg->id,
        'operating_system_id' => $os->id, 'name' => 'pve01',
    ]);

    // Der Weg, der beim Browsertest scheiterte: Das Modal blendet das
    // Standortfeld aus, schickt also nichts mit. Frueher lag die Ableitung im
    // FormRequest - dessen prepareForValidation laeuft hier aber nie, weil
    // ObjektFormular den Request nur fuer seine rules() erzeugt.
    Livewire::test(ObjektFormular::class, ['typ' => 'vm', 'customer' => $customer])
        ->call('neu')
        ->set('form.server_id', $host->id)
        ->set('form.name', 'VM-Modal')
        ->set('form.operating_system_id', $os->id)
        ->call('speichern')
        ->assertHasNoErrors();

    expect(VM::where('name', 'VM-Modal')->first()->site_id)->toBe($hamburg->id);
});

test('der Host eines fremden Kunden steuert keinen Standort bei (IDOR)', function () {
    [$customer, $hamburg, $muenchen, $os] = vmUmgebung();
    [$fremderKunde, $fremdesHamburg] = vmUmgebung();
    $this->actingAs(userWithPermissions(['vm_create']));

    $fremderHost = Server::factory()->create([
        'customer_id' => $fremderKunde->id, 'site_id' => $fremdesHamburg->id,
        'operating_system_id' => $os->id, 'name' => 'fremd01',
    ]);

    $this->post(route('vm.store', $customer), [
        'server_id' => $fremderHost->id, 'site_id' => $hamburg->id,
        'name' => 'VM-Fremd', 'operating_system_id' => $os->id,
    ])->assertSessionHasErrors('server_id');

    expect(VM::where('name', 'VM-Fremd')->exists())->toBeFalse();
});
