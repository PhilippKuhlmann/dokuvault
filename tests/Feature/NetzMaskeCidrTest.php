<?php

use App\Livewire\NetworkQuickCreate;
use App\Models\Customer;
use App\Models\Network;
use App\Models\Site;
use Livewire\Livewire;

test('Maske wird zu CIDR', function (string $maske, ?int $cidr) {
    expect(Network::cidrAusMaske($maske))->toBe($cidr);
})->with([
    ['255.255.255.0', 24],
    ['255.255.0.0', 16],
    ['255.0.0.0', 8],
    ['255.255.255.255', 32],
    ['255.255.255.252', 30],
    ['255.255.254.0', 23],
    ['0.0.0.0', 0],
    [' 255.255.255.0 ', 24],        // Leerzeichen beim Einfuegen aus der Zwischenablage
    ['255.0.255.0', null],          // Loecher in der Maske - keine gueltige Maske
    ['255.255.255.1', null],
    ['300.1.1.1', null],
    ['keine Maske', null],
    ['', null],
]);

test('CIDR wird zu Maske', function (int|string|null $cidr, ?string $maske) {
    expect(Network::maskeAusCidr($cidr))->toBe($maske);
})->with([
    [24, '255.255.255.0'],
    ['24', '255.255.255.0'],
    [16, '255.255.0.0'],
    [8, '255.0.0.0'],
    [32, '255.255.255.255'],
    [30, '255.255.255.252'],
    [0, '0.0.0.0'],
    [33, null],
    [-1, null],
    ['', null],
    [null, null],
    ['abc', null],
]);

test('hin und zurueck ergibt wieder dasselbe', function () {
    foreach (range(0, 32) as $cidr) {
        $maske = Network::maskeAusCidr($cidr);

        expect($maske)->not->toBeNull();
        expect(Network::cidrAusMaske($maske))->toBe($cidr);
    }
});

test('das VLAN-Fenster ergaenzt die fehlende Schreibweise', function () {
    $this->actingAs(userWithPermissions(['network_create']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    // Frueher hing die Ergaenzung im FormRequest der /network/create-Seite.
    // Die gibt es nicht mehr - jetzt ergaenzt das Fenster schon beim Tippen,
    // damit man vor dem Speichern sieht, was herauskommt.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->call('neu')
        ->set('site_id', $site->id)
        ->set('description', 'Nur Maske')
        ->set('network', '10.10.10.0')
        ->set('subnetmask', '255.255.240.0')
        ->call('speichern')
        ->assertHasNoErrors();

    expect((int) Network::where('description', 'Nur Maske')->sole()->cidr)->toBe(20);

    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->call('neu')
        ->set('site_id', $site->id)
        ->set('description', 'Nur CIDR')
        ->set('network', '10.10.11.0')
        ->set('cidr', 26)
        ->call('speichern')
        ->assertHasNoErrors();

    expect(Network::where('description', 'Nur CIDR')->sole()->subnetmask)->toBe('255.255.255.192');
});

test('die zuletzt getippte Angabe zieht die andere nach', function () {
    $this->actingAs(userWithPermissions(['network_create']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    // Unterschied zur alten Seite: Dort blieben widerspruechliche Angaben
    // stehen, wie sie eingetippt waren. Im Fenster sieht man die Ergaenzung
    // dagegen sofort - die zuletzt geaenderte Angabe gewinnt, und beides
    // passt beim Speichern zusammen, statt sich zu widersprechen.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->call('neu')
        ->set('site_id', $site->id)
        ->set('description', 'Von Hand')
        ->set('network', '10.10.12.0')
        ->set('cidr', 16)
        ->set('subnetmask', '255.255.255.0')
        ->call('speichern')
        ->assertHasNoErrors();

    $netz = Network::where('description', 'Von Hand')->sole();

    expect($netz->subnetmask)->toBe('255.255.255.0');
    // Die Maske hat den CIDR nachgezogen - beides passt jetzt zusammen,
    // statt sich zu widersprechen.
    expect((int) $netz->cidr)->toBe(24);
});

test('die vollstaendige Praefixtabelle stimmt in beide Richtungen', function () {
    // Alle 33 Praefixe gegen die uebliche Referenztabelle - nicht gegen die
    // eigene Rechnung. Ein Roundtrip-Test allein waere in sich stimmig und
    // trotzdem falsch, wenn beide Richtungen denselben Denkfehler haetten.
    $tabelle = [
        32 => '255.255.255.255', 31 => '255.255.255.254', 30 => '255.255.255.252',
        29 => '255.255.255.248', 28 => '255.255.255.240', 27 => '255.255.255.224',
        26 => '255.255.255.192', 25 => '255.255.255.128', 24 => '255.255.255.0',
        23 => '255.255.254.0', 22 => '255.255.252.0', 21 => '255.255.248.0',
        20 => '255.255.240.0', 19 => '255.255.224.0', 18 => '255.255.192.0',
        17 => '255.255.128.0', 16 => '255.255.0.0', 15 => '255.254.0.0',
        14 => '255.252.0.0', 13 => '255.248.0.0', 12 => '255.240.0.0',
        11 => '255.224.0.0', 10 => '255.192.0.0', 9 => '255.128.0.0',
        8 => '255.0.0.0', 7 => '254.0.0.0', 6 => '252.0.0.0', 5 => '248.0.0.0',
        4 => '240.0.0.0', 3 => '224.0.0.0', 2 => '192.0.0.0', 1 => '128.0.0.0',
        0 => '0.0.0.0',
    ];

    foreach ($tabelle as $cidr => $maske) {
        expect(Network::maskeAusCidr($cidr))->toBe($maske, "/{$cidr}");
        expect(Network::cidrAusMaske($maske))->toBe($cidr, $maske);
    }
});
