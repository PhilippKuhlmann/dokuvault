<?php

use App\Models\Customer;
use App\Support\NavigationState;

/*
 * Die Seitenleiste listet nur die Uebersichtsseiten. Auf einer Seite darunter -
 * etwa /kunde/checklists/settings - klappte die Gruppe deshalb zu und kein
 * Eintrag war markiert: man sah nicht mehr, wo man ist.
 */

/** Steht der Knopf der Gruppe offen? */
function gruppeOffen(string $html, string $label): bool
{
    return (bool) preg_match(
        '/aria-expanded="true"[^>]*aria-controls="dropdown-'.preg_quote($label, '/').'"/',
        $html
    );
}

/** Ist der Link auf diese Adresse als aktiv markiert? */
function linkAktiv(string $html, string $href): bool
{
    preg_match('/<a href="'.preg_quote(e($href), '/').'"(.*?)>/s', $html, $treffer);

    return isset($treffer[1]) && str_contains($treffer[1], 'border-cerulean-600');
}

it('markiert die Uebersicht und laesst die Gruppe offen, auch auf einer Unterseite', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['checklist_manage']));

    $html = $this->get(route('checklist.settings', $customer))->assertOk()->getContent();

    expect(gruppeOffen($html, 'Kunde'))->toBeTrue()
        ->and(linkAktiv($html, route('checklist.index', $customer)))->toBeTrue();
});

it('markiert die Uebersicht weiter auf der Uebersichtsseite selbst', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['checklist_manage']));

    $html = $this->get(route('checklist.index', $customer))->assertOk()->getContent();

    expect(gruppeOffen($html, 'Kunde'))->toBeTrue()
        ->and(linkAktiv($html, route('checklist.index', $customer)))->toBeTrue();
});

it('oeffnet nur die Gruppe, zu der die Seite gehoert', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['checklist_manage', 'server_viewAny']));

    $html = $this->get(route('checklist.settings', $customer))->assertOk()->getContent();

    expect(gruppeOffen($html, 'Server'))->toBeFalse();
});

/*
 * Der Praefix endet an einer ganzen Pfadstufe. Ohne diese Grenze wuerde
 * /kunde/network auch /kunde/network-map, /kunde/networkshare und
 * /kunde/networkswitch markieren.
 */
it('markiert nur bis zur naechsten Pfadstufe', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['network_viewAny']));

    $this->get(route('networkmap.index', $customer))->assertOk();

    expect(NavigationState::isActive(route('network.index', $customer)))->toBeFalse()
        ->and(NavigationState::isActive(route('networkmap.index', $customer)))->toBeTrue();
});
