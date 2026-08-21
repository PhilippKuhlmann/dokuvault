<?php

use App\Livewire\GlobalSearch;
use App\Livewire\NetworkList;
use App\Livewire\ObjektListe;
use App\Livewire\SearchCustomer;
use App\Models\Camera;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Network;
use App\Models\Site;
use Livewire\Livewire;

/**
 * Der Unterstrich steht in LIKE fuer ein beliebiges Zeichen, das
 * Prozentzeichen fuer beliebig viele. Ohne Maskierung fand "SRV_01" auch
 * "SRV101", und wer "%" eintippte, bekam den ganzen Bestand.
 *
 * Die Tests laufen auf SQLite, die Produktion auf MySQL. Beide behandeln
 * Backslashes in Zeichenketten unterschiedlich - ohne ESCAPE-Klausel findet
 * SQLite mit einem maskierten Muster gar nichts mehr. Diese Tests halten fest,
 * dass es auf der Datenbank funktioniert, auf der sie laufen.
 */
test('die globale Suche liest den Unterstrich als Zeichen', function () {
    $this->actingAs(userWithPermissions(['domain_viewAny']));
    $customer = Customer::factory()->create();

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'srv_01.example']);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'srv101.example']);

    Livewire::test(GlobalSearch::class)
        ->set('search', 'srv_01')
        ->assertSee('srv_01.example')
        ->assertDontSee('srv101.example');
});

test('die globale Suche liefert beim Prozentzeichen nicht alles', function () {
    $this->actingAs(userWithPermissions(['domain_viewAny']));
    $customer = Customer::factory()->create();

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'unauffaellig.de']);

    Livewire::test(GlobalSearch::class)
        ->set('search', '%')
        ->assertDontSee('unauffaellig.de');
});

test('die Geraeteliste liest den Unterstrich als Zeichen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny']));

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'liste_eins.de']);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'listexeins.de']);

    // Hier stand die Maskierung schon, aber ohne ESCAPE - auf SQLite fand die
    // Suche damit gar nichts mehr.
    Livewire::test(ObjektListe::class, ['typ' => 'domain', 'customer' => $customer])
        ->set('search', 'liste_eins')
        ->assertSee('liste_eins.de')
        ->assertDontSee('listexeins.de');
});

test('die VLAN-Liste liest den Unterstrich als Zeichen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['network_viewAny']));

    Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'description' => 'netz_eins']);
    Network::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'description' => 'netzxeins']);

    Livewire::test(NetworkList::class, ['customer' => $customer])
        ->set('search', 'netz_eins')
        ->assertSee('netz_eins')
        ->assertDontSee('netzxeins');
});

test('die Kundensuche liest den Unterstrich als Zeichen', function () {
    $this->actingAs(userWithPermissions([]));

    Customer::factory()->create(['name' => 'Kunde_Eins']);
    Customer::factory()->create(['name' => 'KundeXEins']);

    Livewire::test(SearchCustomer::class)
        ->set('search', 'Kunde_Eins')
        ->assertSee('Kunde_Eins')
        ->assertDontSee('KundeXEins');
});

test('ein gewoehnlicher Begriff findet weiterhin', function () {
    $customer = Customer::factory()->create(['name' => 'Ganz Normal GmbH']);
    $this->actingAs(userWithPermissions([]));

    // Die Maskierung darf die Suche nicht stilllegen - genau das passierte auf
    // SQLite, solange die ESCAPE-Klausel fehlte.
    Livewire::test(SearchCustomer::class)
        ->set('search', 'Normal')
        ->assertSee('Ganz Normal GmbH');
});

test('die globale Suche behaelt ihre Praefix-Form fuer Massentabellen', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs(userWithPermissions(['camera_viewAny']));

    Camera::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'kamera-eingang']);
    Camera::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'alte-kamera-eingang']);

    // Bei Millionen Datensaetzen kostete "%begriff%" 2788 ms, die Praefix-Form
    // auf indizierter Spalte 3 ms. Die Maskierung darf das nicht aufweichen:
    // Wer "kamera-eingang" sucht, findet die eine, aber nicht die, bei der es
    // mittendrin steht.
    Livewire::test(GlobalSearch::class)
        ->set('search', 'kamera-eingang')
        ->assertSee('kamera-eingang')
        ->assertDontSee('alte-kamera-eingang');
});
