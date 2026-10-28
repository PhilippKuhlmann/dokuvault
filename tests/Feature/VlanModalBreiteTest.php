<?php

use App\Livewire\NetworkQuickCreate;
use App\Models\Customer;
use Livewire\Livewire;

/*
 * Ein Eingabefeld bringt rund 220 px Eigenbreite mit (size=20). Eine Spalte mit
 * flex-1 und ohne min-w-0 kann darunter nicht schrumpfen: Zwei davon
 * nebeneinander brauchten 456 px in einem 408 px breiten Modal, DNS 2 und
 * DHCP-Ende standen ausserhalb und man musste nach rechts scrollen.
 */
it('laesst jede mitwachsende Spalte unter die Eigenbreite der Felder schrumpfen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['network_create']));

    $html = Livewire::test(NetworkQuickCreate::class, ['customer' => $customer])
        ->call('neu')->html();

    preg_match_all('/class="[^"]*\bflex-1\b[^"]*"/', $html, $spalten);

    expect($spalten[0])->not->toBeEmpty();

    foreach ($spalten[0] as $klassen) {
        expect($klassen)->toContain('min-w-0');
    }
});
