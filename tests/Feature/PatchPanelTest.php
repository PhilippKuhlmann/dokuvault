<?php

use App\Livewire\GlobalSearch;
use App\Livewire\PatchPanelPorts;
use App\Livewire\RackEditor;
use App\Models\Customer;
use App\Models\NetworkSwitch;
use App\Models\PatchPanel;
use App\Models\PatchPort;
use App\Models\Rack;
use App\Models\RackItem;
use App\Models\Site;
use Livewire\Livewire;

/** @return array{0: Customer, 1: Site, 2: PatchPanel} */
function customerWithPanel(int $portCount = 24, int $heightUnits = 1): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $panel = PatchPanel::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'PF-Test', 'port_count' => $portCount, 'height_units' => $heightUnits,
    ]);
    $panel->syncPorts();

    return [$customer, $site, $panel];
}

function patchTestSwitch(Customer $customer, Site $site, string $name = 'SW-01'): NetworkSwitch
{
    return NetworkSwitch::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => $name, 'ip' => '10.0.0.2',
    ]);
}

// --- CRUD und Portanzahl ---

test('Anlegen erzeugt genau so viele Ports wie angegeben, durchnummeriert', function () {
    $this->actingAs(userWithPermissions(['patchpanel_create']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $this->post("/{$customer->slug}/patchpanel", [
        'site_id' => $site->id, 'name' => 'PF-EG-01', 'port_count' => 48, 'height_units' => 2,
    ]);

    $panel = PatchPanel::firstOrFail();
    expect($panel->ports()->count())->toBe(48);
    expect($panel->ports()->pluck('number')->all())->toBe(range(1, 48));
    // customer_id wird auf die Ports durchgereicht - davon lebt die globale Suche
    expect($panel->ports()->where('customer_id', $customer->id)->count())->toBe(48);
});

test('Portanzahl erhöhen legt nur die fehlenden nach', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(24);
    $panel->ports()->where('number', 3)->update(['outlet' => 'Bleibt stehen']);

    $this->patch("/{$customer->slug}/patchpanel/{$panel->id}", [
        'site_id' => $site->id, 'name' => $panel->name, 'port_count' => 48, 'height_units' => 2,
    ])->assertRedirect("/{$customer->slug}/patchpanel");

    expect($panel->ports()->count())->toBe(48);
    expect($panel->ports()->where('number', 3)->value('outlet'))->toBe('Bleibt stehen');
});

test('Verkleinern wird abgelehnt, solange oben dokumentierte Ports liegen', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(48);
    $panel->ports()->where('number', 40)->update(['outlet' => 'EG 2.14']);

    $this->patch("/{$customer->slug}/patchpanel/{$panel->id}", [
        'site_id' => $site->id, 'name' => $panel->name, 'port_count' => 24, 'height_units' => 1,
    ])->assertSessionHasErrors('port_count');

    expect($panel->fresh()->port_count)->toBe(48);
    expect($panel->ports()->count())->toBe(48);
});

test('Verkleinern geht, wenn oben nichts dokumentiert ist', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(48);
    $panel->ports()->where('number', 3)->update(['outlet' => 'Unten, bleibt']);

    $this->patch("/{$customer->slug}/patchpanel/{$panel->id}", [
        'site_id' => $site->id, 'name' => $panel->name, 'port_count' => 24, 'height_units' => 1,
    ])->assertRedirect("/{$customer->slug}/patchpanel");

    expect($panel->ports()->count())->toBe(24);
    expect($panel->ports()->where('number', 3)->value('outlet'))->toBe('Unten, bleibt');
});

test('Liste zeigt Patchfeld und Dosen, ohne Berechtigung 403', function () {
    $this->actingAs(userWithPermissions(['patchpanel_viewAny']));
    [$customer, $site, $panel] = customerWithPanel(24);
    $panel->ports()->where('number', 5)->update(['outlet' => 'EG 1.05', 'label' => 'Besprechung']);

    $this->get("/{$customer->slug}/patchpanel")->assertStatus(200)
        ->assertSee('PF-Test')->assertSee('EG 1.05')->assertSee('Besprechung');

    nutzerWechseln(userWithPermissions([]));
    $this->get("/{$customer->slug}/patchpanel")->assertStatus(403);
});

test('Papierkorb: löschen, wiederherstellen, Ports überleben', function () {
    $this->actingAs(userWithPermissions(['patchpanel_delete', 'see_hidden']));
    [$customer, $site, $panel] = customerWithPanel(24);
    $panel->ports()->where('number', 1)->update(['outlet' => 'EG 1.01']);

    $this->delete("/{$customer->slug}/patchpanel/{$panel->id}");
    $this->assertSoftDeleted('patch_panels', ['id' => $panel->id]);

    $this->post("/{$customer->slug}/trash/patchpanel/{$panel->id}/restore");
    expect($panel->fresh()->deleted_at)->toBeNull();
    expect($panel->fresh()->ports()->where('number', 1)->value('outlet'))->toBe('EG 1.01');
});

// --- Portbeschriftung (Livewire) ---

test('Speichern schreibt Dose, Switch und Switch-Port', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(4);
    $switch = patchTestSwitch($customer, $site, 'SW-Core');
    $port = $panel->ports()->where('number', 2)->firstOrFail();

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->set("outlet.{$port->id}", 'EG 1.02')
        ->set("label.{$port->id}", 'Empfang')
        ->set("switchId.{$port->id}", $switch->id)
        ->set("switchPort.{$port->id}", '1/0/12')
        ->call('save')
        ->assertHasNoErrors();

    $port->refresh();
    expect($port->outlet)->toBe('EG 1.02');
    expect($port->label)->toBe('Empfang');
    expect($port->network_switch_id)->toBe($switch->id);
    expect($port->switch_port)->toBe('1/0/12');
});

test('Switch eines fremden Kunden wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(4);

    $fremd = Customer::factory()->create();
    $fremdSite = Site::factory()->create(['customer_id' => $fremd->id]);
    $fremdSwitch = patchTestSwitch($fremd, $fremdSite, 'SW-FREMD');

    $port = $panel->ports()->where('number', 1)->firstOrFail();

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->set("switchId.{$port->id}", $fremdSwitch->id)
        ->call('save')
        ->assertHasErrors("switchId.{$port->id}");

    expect($port->fresh()->network_switch_id)->toBeNull();
});

test('Zeile leeren setzt alle vier Felder zurück', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(4);
    $switch = patchTestSwitch($customer, $site);
    $port = $panel->ports()->where('number', 1)->firstOrFail();
    $port->update([
        'outlet' => 'A.12', 'label' => 'weg', 'network_switch_id' => $switch->id,
        'switch_port' => '3', 'note' => 'auch weg',
    ]);

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->call('clearPort', $port->id);

    $port->refresh();
    expect($port->outlet)->toBeNull();
    expect($port->label)->toBeNull();
    expect($port->network_switch_id)->toBeNull();
    expect($port->switch_port)->toBeNull();
    expect($port->note)->toBeNull();
});

test('Portverwaltung verweigert Nutzern ohne patchpanel_update jede Aktion', function () {
    $this->actingAs(userWithPermissions(['patchpanel_viewAny']));
    [$customer, $site, $panel] = customerWithPanel(4);

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->assertStatus(403);
});

// --- Rack-Integration ---

test('Patchfeld ist im Rack einbaubar und übernimmt seine Höhe', function () {
    $this->actingAs(userWithPermissions(['rack_update']));
    [$customer, $site, $panel] = customerWithPanel(48, 2);
    $rack = Rack::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'Rack A', 'height_units' => 42,
    ]);

    Livewire::test(RackEditor::class, ['rack' => $rack, 'customer' => $customer])
        ->call('placeDevice', 'patchpanel', $panel->id, 10)
        ->assertHasNoErrors();

    $item = RackItem::firstOrFail();
    // Nicht 1: die Hoehe kommt jetzt vom Geraet, ein 48er-Feld belegt 2 HE.
    expect($item->height_units)->toBe(2);
    expect($item->faceAppearance())->toBe('patchpanel');
});

test('Zeichnung nutzt die echte Portanzahl', function () {
    // 48 Ports auf 2 HE => zwei Reihen zu 24; 24 Ports auf 1 HE => eine Reihe zu 24.
    $breit = view('components.rack.face', ['appearance' => 'patchpanel', 'he' => 2, 'ports' => 48])->render();
    $schmal = view('components.rack.face', ['appearance' => 'patchpanel', 'he' => 1, 'ports' => 24])->render();

    // Je Port ein Rechteck plus eine Nase; die 48er-Variante zeichnet doppelt so viele.
    expect(substr_count($breit, '<rect'))->toBeGreaterThan(substr_count($schmal, '<rect'));

    // Ohne Angabe bleibt es bei der ueblichen 24er-Reihe
    $ohne = view('components.rack.face', ['appearance' => 'patchpanel', 'he' => 1])->render();
    expect(substr_count($ohne, '<rect'))->toBe(substr_count($schmal, '<rect'));
});

// --- Globale Suche ---

test('globale Suche findet Patchfeld über den Namen und Dose über die Bezeichnung', function () {
    $this->actingAs(userWithPermissions(['patchpanel_viewAny']));
    [$customer, $site, $panel] = customerWithPanel(24);
    $panel->update(['name' => 'PF-Nordflügel']);
    $panel->ports()->where('number', 7)->update(['outlet' => 'EG 2.14', 'label' => 'Besprechung']);

    Livewire::test(GlobalSearch::class)
        ->set('search', 'Nordflügel')
        ->assertSee('PF-Nordflügel');

    Livewire::test(GlobalSearch::class)
        ->set('search', '2.14')
        ->assertSee('EG 2.14');
});

test('Dosennummer und Raum sind getrennte Felder', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update', 'patchpanel_viewAny']));
    [$customer, $site, $panel] = customerWithPanel(4);
    $port = $panel->ports()->where('number', 1)->firstOrFail();

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->set("outlet.{$port->id}", 'A.12')
        ->set("label.{$port->id}", 'Büro Nord')
        ->call('save')->assertHasNoErrors();

    $port->refresh();
    expect($port->outlet)->toBe('A.12');
    expect($port->label)->toBe('Büro Nord');

    // Die Suche findet die reine Dosennummer, auch ohne den Raum
    Livewire::test(GlobalSearch::class)->set('search', 'A.12')->assertSee('A.12');

    // Nur eine Dosennummer ohne Raum gilt bereits als dokumentiert
    $nurDose = $panel->ports()->where('number', 2)->firstOrFail();
    $nurDose->update(['outlet' => '2.23']);
    expect($nurDose->fresh()->isDocumented())->toBeTrue();
});

test('Kunden-Nutzer sieht fremde Dosen nicht in der Suche', function () {
    [$customer, $site, $panel] = customerWithPanel(24);
    $panel->ports()->where('number', 1)->update(['outlet' => 'GEHEIM 9.99']);

    $fremd = Customer::factory()->create();
    $user = userWithPermissions(['patchpanel_viewAny']);
    $user->update(['customer_id' => $fremd->id]);
    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)
        ->set('search', '9.99')
        ->assertDontSee('GEHEIM 9.99');

    expect(PatchPort::where('outlet', 'GEHEIM 9.99')->exists())->toBeTrue();
});

// --- Dosen durchnummerieren ---

test('durchnummerieren zaehlt ab der ersten Dosennummer hoch', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(4);
    $ports = $panel->ports()->orderBy('number')->get();

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->set("outlet.{$ports[0]->id}", '1.01')
        ->call('durchnummerieren')
        ->call('save')
        ->assertHasNoErrors();

    // Fuehrende Null bleibt, das Praefix auch.
    expect($panel->ports()->orderBy('number')->pluck('outlet')->all())
        ->toBe(['1.01', '1.02', '1.03', '1.04']);
});

test('durchnummerieren ueberschreibt eine abweichende Dose nicht', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(4);
    $ports = $panel->ports()->orderBy('number')->get();

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->set("outlet.{$ports[0]->id}", '1.01')
        // Port 3 heisst anders - das soll das Durchzaehlen nicht plattmachen.
        ->set("outlet.{$ports[2]->id}", 'Serverraum')
        ->call('durchnummerieren')
        ->call('save')
        ->assertHasNoErrors();

    expect($panel->ports()->orderBy('number')->pluck('outlet')->all())
        ->toBe(['1.01', '1.02', 'Serverraum', '1.04']);
});

test('durchnummerieren ohne Startwert meldet das, statt stumm nichts zu tun', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(4);

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->call('durchnummerieren')
        ->assertHasErrors();

    expect($panel->ports()->whereNotNull('outlet')->count())->toBe(0);
});

test('Dosen leeren raeumt das Formular, ohne Raum und Switch anzufassen', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(4);
    $ports = $panel->ports()->orderBy('number')->get();

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->set("outlet.{$ports[0]->id}", '9.99')
        ->set("label.{$ports[0]->id}", 'Besprechung')
        ->call('durchnummerieren')
        // Vertippt: alles leeren und mit der richtigen Nummer neu zaehlen.
        ->call('dosenLeeren')
        ->set("outlet.{$ports[0]->id}", '1.01')
        ->call('durchnummerieren')
        ->call('save')
        ->assertHasNoErrors();

    expect($panel->ports()->orderBy('number')->pluck('outlet')->all())
        ->toBe(['1.01', '1.02', '1.03', '1.04']);

    // Der Raum haengt nicht an der Nummerierung und bleibt stehen.
    expect($panel->ports()->orderBy('number')->first()->label)->toBe('Besprechung');
});

test('Dosen leeren wirkt erst mit Speichern', function () {
    $this->actingAs(userWithPermissions(['patchpanel_update']));
    [$customer, $site, $panel] = customerWithPanel(2);
    $port = $panel->ports()->orderBy('number')->first();
    $port->update(['outlet' => '1.01']);

    Livewire::test(PatchPanelPorts::class, ['panel' => $panel, 'customer' => $customer])
        ->call('dosenLeeren');

    // Ein Fehlklick allein darf nichts loeschen.
    expect($port->fresh()->outlet)->toBe('1.01');
});
