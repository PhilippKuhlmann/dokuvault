<?php

use App\Livewire\NetworkQuickCreate;
use App\Models\Customer;
use App\Models\Site;
use Livewire\Livewire;

/**
 * Wie eine Fehlermeldung aussieht, wenn ein Pflichtfeld leer bleibt.
 *
 * Vorher stand nur kleiner roter Text unter dem Feld - in vier verschiedenen
 * Schreibweisen quer durchs Projekt, 18 davon ohne Dunkelmodus-Farbe, und das
 * Feld selbst blieb unveraendert. Man musste den Text lesen, um zu sehen, wo
 * es klemmt.
 */
test('das fehlerhafte Feld markiert sich selbst', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();
    Site::factory()->create(['customer_id' => $kunde->id]);

    $html = Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->call('speichern')
        ->assertHasErrors(['description'])
        ->html();

    // Die Umrandung, nicht nur der Text: Das ist der Unterschied zwischen
    // "irgendwo stimmt was nicht" und "hier".
    expect($html)->toContain('border-red-500')
        // Fuer alle, die Rot und Grau schlecht unterscheiden - und fuer
        // Vorleseprogramme.
        ->toContain('aria-invalid="true"');
});

test('die Meldung trägt eine Farbe für den Dunkelmodus', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    $html = Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->call('speichern')
        ->html();

    // text-red-600 auf dunklem Grund liest sich schlecht. Das fehlte an 18
    // von 37 Stellen.
    expect($html)->toContain('dark:text-red-400');
});

test('über dem Formular steht, dass etwas fehlt', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    // Ohne diesen Satz wirkt ein Dialog, der sich nicht schliesst, wie ein
    // haengender Knopf.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->call('speichern')
        ->assertSee('Bitte die rot markierten Felder prüfen.');
});

test('der Text sagt, was zu tun ist', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    // "Bitte Bezeichnung angeben." statt "Das Feld Bezeichnung ist
    // erforderlich." - der Satz steht direkt unter der Beschriftung, die er
    // sonst wiederholt, und er nennt die Handlung statt eines Zustands.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->call('speichern')
        ->assertSee('Bitte Bezeichnung angeben.');
});

test('ohne Fehler steht keine Meldung da', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->assertDontSee('Bitte die rot markierten Felder prüfen.')
        ->assertDontSee('border-red-500');
});

test('keine Fehlermeldung im Projekt steht ohne Dunkelmodus-Farbe da', function () {
    // Die Invariante: Vier Schreibweisen nebeneinander waren der Anlass. Wer
    // die nächste von Hand schreibt, vergisst wieder die halbe.
    $treffer = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $datei) {
        if (! str_ends_with($datei->getFilename(), '.blade.php')) {
            continue;
        }

        $inhalt = file_get_contents($datei->getPathname());

        // Ein @error-Block, der {{ $message }} selbst ausgibt, statt die
        // Komponente zu benutzen - erlaubt nur mit eigener Dunkelmodus-Farbe.
        preg_match_all('/@error\([^)]*\)(.*?)@enderror/s', $inhalt, $bloecke);

        foreach ($bloecke[1] as $block) {
            if (str_contains($block, '$message') && ! str_contains($block, 'dark:text-red')) {
                $treffer[] = basename($datei->getPathname());
            }
        }
    }

    expect(array_unique($treffer))->toBeEmpty(
        'Fehlermeldung ohne Dunkelmodus-Farbe - x-input.fehler benutzen: '.implode(', ', array_unique($treffer)));
});

/*
|--------------------------------------------------------------------------
| Laufende Prüfung während der Eingabe
|--------------------------------------------------------------------------
*/

test('vor dem ersten Absenden meckert das Formular nicht', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    // Wer anfängt zu tippen, hat das Feld noch nicht fertig. Eine Meldung nach
    // dem ersten Zeichen wäre Meckern, kein Hinweis.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->set('network', '10.')
        ->assertHasNoErrors();
});

test('nach einem abgewiesenen Absenden verschwindet Rot, sobald der Wert stimmt', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    $test = Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->call('speichern')
        ->assertHasErrors(['description', 'network']);

    // Ein gültiger Wert räumt genau dieses Feld frei - die übrigen bleiben rot.
    $test->set('description', 'Clients')
        ->assertHasNoErrors('description')
        ->assertHasErrors(['network']);
});

test('ein weiterhin falscher Wert bleibt rot', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    // Sonst wäre es nur ein Ausblenden der Farbe, keine Prüfung.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->call('speichern')
        ->set('network', 'keine-adresse')
        ->assertHasErrors(['network']);
});

test('Maske und CIDR räumen sich gegenseitig frei', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    // Die Maske setzt das CIDR-Feld mit. Dessen Hook läuft erst nach updated(),
    // ohne Nacharbeit bliebe der rote Rahmen dort stehen.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->set('subnetmask', 'unfug')
        ->call('speichern')
        ->assertHasErrors(['subnetmask'])
        ->set('subnetmask', '255.255.255.0')
        ->assertHasNoErrors(['subnetmask', 'cidr']);
});

test('ein neuer Anlauf beginnt wieder ohne Meckern', function () {
    $nutzer = userWithPermissions(['network_viewAny', 'network_create']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();

    // Nach "Neu" ist das Formular leer - dann darf es nicht sofort rot sein,
    // nur weil vorher einmal etwas gefehlt hat.
    Livewire::test(NetworkQuickCreate::class, ['customer' => $kunde])
        ->call('neu')
        ->call('speichern')
        ->assertHasErrors()
        ->call('neu')
        ->assertHasNoErrors()
        ->set('network', '10.')
        ->assertHasNoErrors();
});
