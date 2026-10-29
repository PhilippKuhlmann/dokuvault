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

/** How the URL stands in the target list: plain JSON via @alpineJson, slashes unescaped. */
function paletteUrl(string $url): string
{
    return $url;
}

test('die Checklisten stehen in der Palette, die Einstellungen nur fuer die IT', function () {
    $customer = Customer::factory()->create();
    $liste = route('checklist.index', $customer);
    $einstellungen = route('checklist.settings', $customer);

    // Wer neue Mitarbeiter meldet, findet die Checklisten, aber nicht die Einstellungen.
    $this->actingAs(userWithPermissions(['server_viewAny', 'checklist_request']))
        ->get("/{$customer->slug}/server")
        ->assertOk()
        ->assertSee(paletteUrl($liste), false)
        ->assertDontSee(paletteUrl($einstellungen), false);

    $this->flushSession();
    $this->actingAs(userWithPermissions(['server_viewAny', 'checklist_manage']))
        ->get("/{$customer->slug}/server")
        ->assertOk()
        ->assertSee(paletteUrl($einstellungen), false);

    $this->flushSession();
    $this->actingAs(userWithPermissions(['server_viewAny']))
        ->get("/{$customer->slug}/server")
        ->assertOk()
        ->assertDontSee(paletteUrl($liste), false);
});
