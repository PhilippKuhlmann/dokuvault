<?php

use App\Models\Role;

/*
 * Die beiden "Alle auswählen"-Haken laufen über closest(): Der Haken sucht
 * von sich aus nach oben das Element, dessen Kästchen er setzen soll.
 *
 * Genau daran war der im Admin-Kasten kaputt: data-admin-block sass am Raster
 * unter der Kopfzeile, der Haken steht aber IN der Kopfzeile. Das Attribut war
 * damit ein Geschwister und kein Vorfahre - closest() lieferte null, der
 * Aufruf warf, und der Haken tat nichts. Zu sehen war davon nichts; der Haken
 * setzte sich, die Rechte blieben leer.
 *
 * Der Test prueft die Beziehung, die closest() braucht, und nicht die Optik:
 * Jeder Haken muss innerhalb des Elements liegen, nach dem er sucht.
 */
function hakenLiegtInSeinemZiel(string $html, string $attribut): bool
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    $pfade = new DOMXPath($dom);

    // Ein input, dessen onchange nach $attribut sucht - und das innerhalb
    // eines Elements mit genau diesem Attribut steht.
    $treffer = $pfade->query(
        sprintf('//*[@%s]//input[contains(@onchange, "%s")]', $attribut, $attribut)
    );

    return $treffer !== false && $treffer->length === 1;
}

test('beide Alle-auswählen-Haken finden ihr Ziel über closest', function () {
    $rolle = Role::factory()->create();

    $html = $this->actingAs(userWithPermissions(['admin_role']))
        ->get(route('admin.role.edit', $rolle))
        ->assertOk()
        ->getContent();

    // Beide Haken sind ueberhaupt da - sonst prueft der Rest nichts.
    expect(substr_count($html, __('Alle auswählen')))->toBe(2);

    expect(hakenLiegtInSeinemZiel($html, 'data-perm-root'))
        ->toBeTrue('Der Haken über der Matrix liegt nicht in [data-perm-root].');

    expect(hakenLiegtInSeinemZiel($html, 'data-admin-block'))
        ->toBeTrue('Der Haken im Admin-Kasten liegt nicht in [data-admin-block] - closest() findet nichts.');
});
