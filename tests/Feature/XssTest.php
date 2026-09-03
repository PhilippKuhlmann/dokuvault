<?php

use App\Models\Customer;
use App\Models\Firewall;
use App\Models\Site;
use App\Support\Adresse;

// --- Adressen, die sich anklicken lassen -------------------------------------

test('nur http und https werden verlinkt', function () {
    // Positivliste: Andersherum müsste man jedes Schema kennen, das ein
    // Browser ausführt - javascript, data, vbscript, und das nächste.
    expect(Adresse::sicher('https://example.com'))->toBe('https://example.com')
        ->and(Adresse::sicher('http://192.168.178.1'))->toBe('http://192.168.178.1')
        ->and(Adresse::sicher('javascript:alert(1)'))->toBeNull()
        ->and(Adresse::sicher('JaVaScRiPt://x/%0aalert(1)'))->toBeNull()
        ->and(Adresse::sicher('data:text/html,<script>alert(1)</script>'))->toBeNull()
        // Eine nackte IP ist keine Adresse zum Anklicken, aber sie soll
        // weiterhin in der Karte stehen.
        ->and(Adresse::sicher('192.168.178.1'))->toBeNull()
        ->and(Adresse::sicher(null))->toBeNull();
});

test('der eigene Pfad wird verlinkt, was nur so aussieht nicht', function () {
    // Die Kundenliste im Adminbereich verlinkt "/" . $customer->slug. Beim
    // ersten Anlauf liess die Positivliste nur http und https durch - und
    // sperrte damit die eigene Anwendung aus.
    expect(Adresse::sicher('/mustermann'))->toBe('/mustermann')
        ->and(Adresse::sicher('/kunde/geraet?seite=2'))->toBe('/kunde/geraet?seite=2')
        // Protokoll-relativ: sieht aus wie ein Pfad, fuehrt aber nach draussen.
        ->and(Adresse::sicher('//evil.example'))->toBeNull()
        // Browser behandeln den Backslash wie den zweiten Schraegstrich.
        ->and(Adresse::sicher('/\\evil.example'))->toBeNull()
        ->and(Adresse::sicher('/'))->toBe('/');
});

test('die Kundenliste im Adminbereich verlinkt den Kunden', function () {
    // Genau der Link, der ausgefallen ist: In der Spalte "URL" stand
    // "/mustermann" nur noch als Text da.
    $this->actingAs(userWithPermissions(['admin_customer']));

    $kunde = Customer::factory()->create();

    $this->get('/admin/customer')
        ->assertStatus(200)
        ->assertSee('href="/'.$kunde->slug.'"', false);
});

test('ein javascript-Link an einer Firewall wird nicht verlinkt', function () {
    // Diese Felder werden nur auf ihre Länge geprüft - dort steht oft eine
    // nackte IP, eine strenge Regel wäre falsch. Der Schutz gehört deshalb
    // dorthin, wo der Link entsteht: Sonst führte jeder Klick den Code aus,
    // in der Sitzung dessen, der klickt.
    $nutzer = userWithPermissions(['firewall_viewAny', 'see_hidden']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);

    Firewall::create([
        'customer_id' => $kunde->id,
        'site_id' => $standort->id,
        'name' => 'Perimeter',
        'url_user' => 'javascript:alert(document.cookie)',
        'url_external' => 'https://firewall.example.com',
    ]);

    $antwort = $this->get("/{$kunde->slug}/firewall");

    $antwort->assertStatus(200)
        // Der harmlose Link steht als Link da ...
        ->assertSee('href="https://firewall.example.com"', false)
        // ... der andere nicht.
        ->assertDontSee('href="javascript:', false);

    // Der Wert selbst bleibt sichtbar - er ist ja dokumentiert worden.
    $antwort->assertSee('javascript:alert(document.cookie)');
});

test('verlinkt wird nach dem Wert, nicht nach der Beschriftung', function () {
    // Die Verwaltungsoberfläche einer Firewall heißt in der Karte
    // "Oberfläche". Die Bedingung zählte vier Beschriftungen auf - "URL",
    // "Admin URL", "User URL", "Externe URL" -, und "Oberfläche" stand nicht
    // darunter. Dort steht eine https-Adresse, und sie war nie anklickbar.
    //
    // Wer ein neues URL-Feld anlegt, müsste sonst eine Liste pflegen, von der
    // er nichts weiß. Geprüft wird deshalb der Wert.
    $nutzer = userWithPermissions(['firewall_viewAny', 'see_hidden']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);

    Firewall::create([
        'customer_id' => $kunde->id,
        'site_id' => $standort->id,
        'name' => 'Perimeter',
        'management_url' => 'https://192.168.175.1:11115',
    ]);

    $this->get("/{$kunde->slug}/firewall")
        ->assertStatus(200)
        ->assertSee('href="https://192.168.175.1:11115"', false);
});

test('eine nackte Adresse ohne Schema bleibt Text', function () {
    // In diesen Feldern steht oft nur eine IP. Sie als Link auszugeben hieße:
    // ein Klick landet auf /kunde/192.168.178.1 - ein Link, der nirgendwo
    // hinführt, sieht schlimmer aus als gar keiner.
    $nutzer = userWithPermissions(['firewall_viewAny', 'see_hidden']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);

    Firewall::create([
        'customer_id' => $kunde->id,
        'site_id' => $standort->id,
        'name' => 'Perimeter',
        'management_url' => '192.168.178.1',
    ]);

    $antwort = $this->get("/{$kunde->slug}/firewall");

    $antwort->assertStatus(200)
        ->assertDontSee('href="192.168.178.1"', false)
        ->assertSee('192.168.178.1');
});

test('ein Kennwort bleibt maskiert, auch wenn es wie eine Adresse aussieht', function () {
    // Die Wertprüfung steht hinter den Geheimnissen, nicht davor. Sonst wäre
    // aus einem Kennwort ein sichtbarer Link geworden.
    $nutzer = userWithPermissions(['firewall_viewAny', 'see_hidden']);
    $this->actingAs($nutzer);

    $kunde = Customer::factory()->create();
    $standort = Site::factory()->create(['customer_id' => $kunde->id]);

    Firewall::create([
        'customer_id' => $kunde->id,
        'site_id' => $standort->id,
        'name' => 'Perimeter',
        'cloud_backup_password' => 'https://nicht-anklicken.example',
    ]);

    $this->get("/{$kunde->slug}/firewall")
        ->assertStatus(200)
        ->assertDontSee('href="https://nicht-anklicken.example"', false);
});

// --- Werte in Alpine-Ausdrücken ---------------------------------------------

test('kein Alpine-Ausdruck setzt einen Wert in Anführungszeichen zusammen', function () {
    // Im Attribut entschlüsselt der Browser den Wert, bevor Alpine ihn
    // auswertet - aus &#039; wird wieder ein Anführungszeichen, und der
    // Ausdruck lässt sich verlassen. Nachgestellt und im Browser belegt:
    // "x', a: (window.xssBeweis=1), b: 'y" als abgewiesener Wert kam über
    // old() zurück und führte den Code aus.
    //
    // Richtig ist @js(...) - das erzeugt gültiges JavaScript aus dem Wert.
    $treffer = [];

    foreach (rekursiveBladeDateien() as $datei) {
        // Blade-Kommentare vorher heraus: Sie landen nicht im HTML, und ein
        // Kommentar, der genau diese Schreibweise erklärt, wäre sonst selbst
        // ein Treffer.
        $inhalt = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($datei));

        if (preg_match_all('/x-data="\{[^"]*\'\{\{/', $inhalt, $t)) {
            $treffer[] = str_replace(base_path().'/', '', $datei);
        }
    }

    expect($treffer)->toBe([], 'Wert in Anführungszeichen im Alpine-Ausdruck: '.implode(', ', $treffer));
});

function rekursiveBladeDateien(): array
{
    $dateien = [];

    $lauf = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($lauf as $datei) {
        if ($datei->isFile() && str_ends_with($datei->getFilename(), '.blade.php')) {
            $dateien[] = $datei->getPathname();
        }
    }

    return $dateien;
}
