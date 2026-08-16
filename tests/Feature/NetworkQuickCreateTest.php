<?php

use App\Livewire\NetworkList;
use App\Livewire\NetworkQuickCreate;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * Ein VLAN anlegen, ohne die Seite zu wechseln. Dieselbe Komponente an zwei
 * Stellen: am Geraet (Standort ist vorgegeben) und ueber der VLAN-Liste
 * (Standort wird gewaehlt).
 */
function netzUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

test('mit vorgegebenem Standort entfaellt die Auswahl und das Netz erbt ihn', function () {
    $this->actingAs(userWithPermissions(['network_create']));
    [$customer, $site] = netzUmgebung();

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer, 'siteId' => $site->id])
        ->set('description', 'DMZ')
        ->set('vlanId', 90)
        ->set('network', '10.10.90.0')
        ->set('cidr', 24)
        ->set('gateway', '10.10.90.1')
        ->set('dns1', '10.10.30.10')
        ->set('dhcpStart', '10.10.90.100')
        ->set('dhcpEnd', '10.10.90.200')
        ->call('speichern')
        ->assertHasNoErrors()
        // Der IP-Block haengt daran und waehlt das neue Netz aus.
        ->assertDispatched('vlan-angelegt')
        ->assertDispatched('hinweis')
        // Modal zu und Felder geleert - sonst steht die Eingabe beim naechsten
        // Oeffnen noch da.
        ->assertSet('offen', false)
        ->assertSet('description', '');

    $netz = Network::where('description', 'DMZ')->firstOrFail();
    expect($netz->customer_id)->toBe($customer->id);
    expect($netz->site_id)->toBe($site->id);
    expect($netz->vlanId)->toBe(90);
    expect($netz->cidr)->toBe('24');
    expect($netz->gateway)->toBe('10.10.90.1');
    expect($netz->dhcpEnd)->toBe('10.10.90.200');
});

test('ohne vorgegebenen Standort ist die Auswahl Pflicht', function () {
    $this->actingAs(userWithPermissions(['network_create']));
    [$customer] = netzUmgebung();

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->set('description', 'Ohne Standort')
        ->set('network', '10.10.91.0')
        ->call('speichern')
        ->assertHasErrors('site_id');

    expect(Network::count())->toBe(0);
});

test('ein Standort eines fremden Kunden wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['network_create']));
    [$customer] = netzUmgebung();

    $fremd = Customer::factory()->create();
    $fremdeSite = Site::factory()->create(['customer_id' => $fremd->id]);

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->set('site_id', $fremdeSite->id)
        ->set('description', 'Geklaut')
        ->set('network', '10.10.92.0')
        ->call('speichern')
        ->assertHasErrors('site_id');

    expect(Network::count())->toBe(0);
});

test('ohne network_create wird nichts angelegt', function () {
    $this->actingAs(userWithPermissions([]));
    [$customer, $site] = netzUmgebung();

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer, 'siteId' => $site->id])
        ->set('description', 'Heimlich')
        ->set('network', '10.10.99.0')
        ->call('speichern')
        ->assertForbidden();

    expect(Network::count())->toBe(0);
});

test('die VLAN-Liste bindet das Modal ein', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_create']));
    [$customer] = netzUmgebung();

    // In der Liste traegt der Knopf die Beschriftung des bisherigen "Neu" -
    // gleiche Stelle, gleiche Optik, nur ohne Seitenwechsel.
    $inhalt = $this->get("/{$customer->slug}/network")->assertOk()->getContent();

    expect($inhalt)->toContain("wire:click=\"\$set('offen', true)\"");
    expect($inhalt)->toContain('bg-cerulean-600');
    // Der alte Weg ist nicht mehr verlinkt.
    expect($inhalt)->not->toContain('/network/create');
});

test('die Liste rendert nach dem Anlegen neu, ohne Seitenwechsel', function () {
    $this->actingAs(userWithPermissions(['network_viewAny', 'network_create']));
    [$customer, $site] = netzUmgebung();

    // Seit die Liste selbst Livewire ist, genuegt das Event - kein Redirect
    // mehr, der auf der Update-Adresse landen oder den Dunkelmodus kosten kann.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->set('site_id', $site->id)
        ->set('description', 'Frisch')
        ->set('network', '10.10.93.0')
        ->call('speichern')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertDispatched('vlan-angelegt');

    Livewire::test(NetworkList::class, ['customer' => $customer])
        ->assertSee('Frisch');
});

test('der Stift oeffnet dasselbe Modal mit geladenen Werten', function () {
    $this->actingAs(userWithPermissions(['network_update']));
    [$customer, $site] = netzUmgebung();

    $netz = Network::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Alt', 'vlanId' => 10, 'network' => '10.10.10.0',
        'subnetmask' => '255.255.255.0', 'gateway' => '10.10.10.1',
    ]);

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->call('bearbeiten', $netz->id)
        ->assertSet('offen', true)
        ->assertSet('bearbeiteId', $netz->id)
        ->assertSet('description', 'Alt')
        ->assertSet('gateway', '10.10.10.1')
        // Aendern und speichern legt kein zweites Netz an.
        ->set('description', 'Neu benannt')
        ->call('speichern')
        ->assertHasNoErrors();

    expect(Network::count())->toBe(1);
    expect($netz->fresh()->description)->toBe('Neu benannt');
});

test('ein fremdes Netz laesst sich nicht ueber die ID oeffnen', function () {
    $this->actingAs(userWithPermissions(['network_update']));
    [$customer] = netzUmgebung();

    $fremd = Customer::factory()->create();
    $fremdeSite = Site::factory()->create(['customer_id' => $fremd->id]);
    $fremdesNetz = Network::create([
        'customer_id' => $fremd->id, 'site_id' => $fremdeSite->id,
        'description' => 'Fremd', 'network' => '10.99.0.0', 'subnetmask' => '255.255.255.0',
    ]);

    // bearbeiteId kommt aus dem Browser - ohne Kundenpruefung waere das ein IDOR.
    expect(fn () => Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->call('bearbeiten', $fremdesNetz->id))
        ->toThrow(ModelNotFoundException::class);
});

test('im Bearbeiten-Modal laesst sich das VLAN loeschen', function () {
    $this->actingAs(userWithPermissions(['network_update', 'network_delete']));
    [$customer, $site] = netzUmgebung();

    $netz = Network::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Weg damit', 'network' => '10.10.11.0', 'subnetmask' => '255.255.255.0',
    ]);

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->call('bearbeiten', $netz->id)
        // Erst die Rueckfrage im Modal, dann das Loeschen - kein Browser-Dialog.
        ->assertSet('loeschenGefragt', false)
        ->set('loeschenGefragt', true)
        ->call('loeschen')
        ->assertSet('offen', false)
        ->assertSet('loeschenGefragt', false)
        // Die Liste laedt daraufhin neu.
        ->assertDispatched('vlan-angelegt');

    // Papierkorb, nicht endgueltig - wie beim Loeschen ueber die alte Seite.
    expect(Network::count())->toBe(0);
    expect(Network::withTrashed()->count())->toBe(1);
});

test('ohne network_delete bleibt das VLAN bestehen', function () {
    $this->actingAs(userWithPermissions(['network_update']));
    [$customer, $site] = netzUmgebung();

    $netz = Network::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'description' => 'Bleibt', 'network' => '10.10.12.0', 'subnetmask' => '255.255.255.0',
    ]);

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->call('bearbeiten', $netz->id)
        ->call('loeschen')
        ->assertForbidden();

    expect(Network::count())->toBe(1);
});

test('jede Aktion meldet sich unten rechts mit eigenem Wortlaut', function () {
    $this->actingAs(userWithPermissions([
        'network_viewAny', 'network_create', 'network_update', 'network_delete',
    ]));
    [$customer, $site] = netzUmgebung();

    // Der Wortlaut ist der Punkt: "angelegt" und "gespeichert" gehen durch
    // dieselbe Methode und haben sich frueher schon einmal vertauscht, weil das
    // dispatch nach dem reset() stand und bearbeiteId dort bereits leer war.
    $komponente = Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->set('site_id', $site->id)
        ->set('description', 'Meldungsprobe')
        ->set('network', '10.10.94.0')
        ->call('speichern')
        ->assertDispatched('hinweis', text: __('VLAN angelegt.'));

    $netz = Network::where('description', 'Meldungsprobe')->sole();

    $komponente
        ->call('bearbeiten', $netz->id)
        ->call('speichern')
        ->assertDispatched('hinweis', text: __('VLAN gespeichert.'))
        ->call('bearbeiten', $netz->id)
        ->call('loeschen')
        ->assertDispatched('hinweis', text: __('VLAN gelöscht.'));
});

test('die VLAN-Liste laesst sich durchsuchen', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = netzUmgebung();

    $netz = fn (array $werte) => Network::factory()->create($werte + [
        'customer_id' => $customer->id,
        'site_id' => $site->id,
    ]);

    $netz(['description' => 'Serverraum', 'vlanId' => 30, 'network' => '10.10.30.0', 'gateway' => '10.10.30.1']);
    $netz(['description' => 'Gaeste-WLAN', 'vlanId' => 40, 'network' => '10.10.40.0', 'gateway' => '10.10.40.1']);

    // Je ein Begriff aus den vier durchsuchten Spalten - Bezeichnung, Nummer,
    // Netz und Gateway. Jeder muss genau eines der beiden Netze treffen.
    $faelle = [
        'Gaeste' => 'Gaeste-WLAN',      // Bezeichnung
        '40' => 'Gaeste-WLAN',          // VLAN-Nummer
        '10.10.30.0' => 'Serverraum',   // Netz
        '10.10.30.1' => 'Serverraum',   // Gateway
    ];

    foreach ($faelle as $begriff => $treffer) {
        $daneben = $treffer === 'Serverraum' ? 'Gaeste-WLAN' : 'Serverraum';

        Livewire::test(NetworkList::class, ['customer' => $customer])
            ->set('search', (string) $begriff)
            ->assertSee($treffer)
            ->assertDontSee($daneben);
    }
});

test('ohne Treffer sagt die Liste, dass der Begriff nicht passt', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = netzUmgebung();

    Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'description' => 'Clients']);

    // Ohne Begriff heisst es "noch keine Eintraege" - das waere hier gelogen.
    Livewire::test(NetworkList::class, ['customer' => $customer])
        ->set('search', 'gibtesnicht')
        ->assertSee('Kein VLAN passt zu')
        ->assertDontSee('Noch keine Einträge vorhanden.');
});

test('die Suche bleibt am Kunden haengen', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = netzUmgebung();

    // Beide Kunden benutzen denselben privaten Adressbereich - der Normalfall.
    $fremder = Customer::factory()->create();
    Network::factory()->create([
        'customer_id' => $fremder->id,
        'site_id' => Site::factory()->create(['customer_id' => $fremder->id])->id,
        'description' => 'Fremdnetz',
        'network' => '192.168.178.0',
        'gateway' => '192.168.178.1',
    ]);
    Network::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'description' => 'Eigennetz',
        'network' => '192.168.178.0',
        'gateway' => '192.168.178.1',
    ]);

    // Der Punkt ist die Klammer um die vier ODER-Bedingungen. Ohne sie liest
    // MySQL "customer_id = X AND description LIKE ... OR network LIKE ..." -
    // die spaeteren ODER-Zweige stehen dann ohne Kundenfilter da und die
    // Suche nach einer Adresse zeigt die VLANs aller Kunden.
    Livewire::test(NetworkList::class, ['customer' => $customer])
        ->set('search', '192.168.178.0')
        ->assertSee('Eigennetz')
        ->assertDontSee('Fremdnetz');
});

test('nach dem Tippen steht die Liste wieder auf Seite eins', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer, $site] = netzUmgebung();

    Network::factory()->count(30)->create(['customer_id' => $customer->id, 'site_id' => $site->id]);

    Livewire::test(NetworkList::class, ['customer' => $customer])
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('search', 'irgendwas')
        ->assertSet('paginators.page', 1);
});

test('die Ueberschrift bleibt beim Rerender stehen', function () {
    $this->actingAs(userWithPermissions(['network_viewAny']));
    [$customer] = netzUmgebung();

    // x-sitetopmenu leitet den Titel sonst aus dem Routennamen ab. Beim
    // Livewire-Update heisst die Route "livewire.update" - die Ueberschrift
    // verschwand nach jeder Suche, jedem Anlegen und jedem Loeschen.
    //
    // Geprueft wird der Titel-Container, nicht bloss das Wort: "VLANs" steht
    // auch in der Vorleser-Beschriftung des Suchfelds, ein assertSee waere
    // immer gruen.
    $ueberschrift = '/font-CoconPro[^>]*>\s*VLANs\s*</';

    $komponente = Livewire::test(NetworkList::class, ['customer' => $customer]);
    expect($komponente->html())->toMatch($ueberschrift);

    expect($komponente->set('search', 'egal')->html())->toMatch($ueberschrift);
});
