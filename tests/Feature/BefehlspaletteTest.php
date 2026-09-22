<?php

use App\Models\Customer;

/*
 * Die Befehlspalette (Cmd/Strg+K) hängt in den Layouts und sammelt ihre Ziele
 * serverseitig nach Rechten. Geprüft wird, dass sie sich auf beiden Layouts
 * ohne Fehler rendert - ein falscher Routenname oder ein Fehler beim Aufbau
 * der Zielliste würde die ganze Seite zerlegen.
 */
test('die Palette ist auf einer Kundenseite eingebunden', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithPermissions(['server_viewAny']))
        ->get("/{$customer->slug}/server")
        ->assertOk()
        ->assertSee(__('Seite suchen … (z. B. „Server“)'));
});

test('die Palette ist im Admin-Bereich eingebunden', function () {
    $this->actingAs(userWithPermissions(['admin_user']))
        ->get(route('admin.user.index'))
        ->assertOk()
        ->assertSee(__('Seite suchen … (z. B. „Server“)'));
});
