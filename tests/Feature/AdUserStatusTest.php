<?php

use App\Livewire\ObjektFormular;
use App\Livewire\ObjektListe;
use App\Models\ADUser;
use App\Models\Customer;
use Database\Seeders\LocalDatabaseSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Der Status eines AD-Benutzers.
 *
 * Gemeldet: In der Liste stand "—", im Bearbeiten-Fenster aber "Aktiv". Eine
 * Auswahl ohne passenden Eintrag zeigt ihren ersten an - das Fenster behauptete
 * damit einen Wert, den es nirgends gab.
 */
function adBenutzer(?bool $status): array
{
    $customer = Customer::factory()->create();
    $benutzer = ADUser::create([
        'customer_id' => $customer->id,
        'username' => 'mmuster',
        'firstName' => 'Max',
        'enabled' => $status,
    ]);

    return [$customer, $benutzer];
}

test('ohne gespeicherten Status behauptet das Fenster keinen', function () {
    $this->actingAs(userWithPermissions(['aduser_update', 'aduser_viewAny']));
    [$customer, $benutzer] = adBenutzer(null);

    $html = Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $benutzer->id)
        ->html();

    // Der Eintrag steht vor "Aktiv" und ist damit der ausgewaehlte.
    expect($html)->toContain('unbekannt');
    expect(strpos($html, 'unbekannt'))->toBeLessThan(strpos($html, 'Aktiv'));
});

test('mit gespeichertem Status gibt es den Zusatzeintrag nicht', function () {
    $this->actingAs(userWithPermissions(['aduser_update', 'aduser_viewAny']));
    [$customer, $benutzer] = adBenutzer(true);

    $html = Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $benutzer->id)
        ->html();

    expect(str_contains($html, 'unbekannt'))
        ->toBeFalse('Ist der Status bekannt, gehört der Zusatzeintrag nicht in die Auswahl.');
});

test('auch der Wert 0 gilt als bekannt', function () {
    $this->actingAs(userWithPermissions(['aduser_update', 'aduser_viewAny']));
    [$customer, $benutzer] = adBenutzer(false);

    // 0 ist ein Wert, kein fehlender - eine lose Pruefung haette ihn mit dem
    // Leerstring verwechselt und "unbekannt" angeboten.
    $html = Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $benutzer->id)
        ->html();

    expect(str_contains($html, 'unbekannt'))->toBeFalse('0 ist "Deaktiviert", nicht "unbekannt".');
});

test('Fenster und Liste sagen dasselbe', function () {
    $this->actingAs(userWithPermissions(['aduser_update', 'aduser_viewAny']));
    [$customer, $benutzer] = adBenutzer(null);

    // Der gemeldete Widerspruch: hier "—", dort "Aktiv".
    $liste = Livewire::test(ObjektListe::class, ['typ' => 'aduser', 'customer' => $customer])->html();
    expect($liste)->toContain('—');
    expect(str_contains($liste, 'Aktiv'))->toBeFalse('Ohne Wert darf die Liste keinen Status nennen.');
});

test('Speichern ohne Status laesst ihn leer, statt Aktiv daraus zu machen', function () {
    $this->actingAs(userWithPermissions(['aduser_update', 'aduser_viewAny']));
    [$customer, $benutzer] = adBenutzer(null);

    Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $benutzer->id)
        ->call('speichern')
        ->assertHasNoErrors();

    // Ein unbekannter Status darf durch blosses Speichern nicht zu einer
    // Aussage werden.
    expect($benutzer->fresh()->enabled)->toBeNull();
});

test('ein gewaehlter Status wird gespeichert', function () {
    $this->actingAs(userWithPermissions(['aduser_update', 'aduser_viewAny']));
    [$customer, $benutzer] = adBenutzer(null);

    Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $benutzer->id)
        ->set('form.enabled', '0')
        ->call('speichern')
        ->assertHasNoErrors();

    expect($benutzer->fresh()->enabled)->toBeFalse();
});

test('die Demo-Daten setzen einen Status', function () {
    $customer = Customer::factory()->create();
    ADUser::factory(20)->create(['customer_id' => $customer->id]);

    // Vorher stand bei jedem Demo-Benutzer "—" in der Spalte Status.
    expect(ADUser::where('customer_id', $customer->id)->whereNull('enabled')->count())->toBe(0);
});

test('ein deaktivierter Benutzer steht im Fenster auch auf Deaktiviert', function () {
    $this->actingAs(userWithPermissions(['aduser_update', 'aduser_viewAny']));
    [$customer, $benutzer] = adBenutzer(false);

    // Der schwerere Fall des gemeldeten Fehlers: (string) false ergibt '' und
    // nicht '0'. Das Feld kam leer im Formular an, die Auswahl zeigte ihren
    // ersten Eintrag - und behauptete damit das Gegenteil des Gespeicherten.
    $formular = Livewire::test(ObjektFormular::class, ['typ' => 'aduser', 'customer' => $customer])
        ->call('bearbeiten', 'aduser', $benutzer->id);

    expect($formular->get('form')['enabled'])->toBe('0');

    // Und blosses Speichern darf ihn nicht aktivieren.
    $formular->call('speichern')->assertHasNoErrors();
    expect($benutzer->fresh()->enabled)->toBeFalse();
});

test('die Liste zeigt Haken, Kreuz und Strich statt Woertern', function () {
    $this->actingAs(userWithPermissions(['aduser_viewAny', 'aduser_update']));
    $customer = Customer::factory()->create();

    foreach ([true => 'aktiv', false => 'gesperrt'] as $status => $name) {
        ADUser::create([
            'customer_id' => $customer->id, 'username' => $name, 'enabled' => $status,
        ]);
    }
    ADUser::create(['customer_id' => $customer->id, 'username' => 'unklar', 'enabled' => null]);

    $html = Livewire::test(ObjektListe::class, ['typ' => 'aduser', 'customer' => $customer])->html();

    // Ein Zeichen erfasst man schneller als ein Wort; die Bedeutung haengt
    // trotzdem nicht allein an der Form, sondern steht im title.
    expect(substr_count($html, '<title>Aktiv</title>'))->toBe(1);
    expect(substr_count($html, '<title>Deaktiviert</title>'))->toBe(1);
    expect($html)->toContain('—');
});

test('eine gesperrte Zeile tritt zurueck, eine aktive nicht', function () {
    $this->actingAs(userWithPermissions(['aduser_viewAny', 'aduser_update']));
    $customer = Customer::factory()->create();
    ADUser::create(['customer_id' => $customer->id, 'username' => 'aktiv', 'enabled' => true]);

    $nurAktiv = Livewire::test(ObjektListe::class, ['typ' => 'aduser', 'customer' => $customer])->html();
    expect(str_contains($nurAktiv, 'opacity-60'))
        ->toBeFalse('Ein aktives Konto ist nichts, was zuruecktreten soll.');

    ADUser::create(['customer_id' => $customer->id, 'username' => 'gesperrt', 'enabled' => false]);

    $mitGesperrt = Livewire::test(ObjektListe::class, ['typ' => 'aduser', 'customer' => $customer])->html();
    expect(substr_count($mitGesperrt, 'opacity-60'))->toBe(1);
});

test('ein unbekannter Status graut die Zeile nicht aus', function () {
    $this->actingAs(userWithPermissions(['aduser_viewAny', 'aduser_update']));
    $customer = Customer::factory()->create();
    ADUser::create(['customer_id' => $customer->id, 'username' => 'unklar', 'enabled' => null]);

    // Nur "gesperrt" tritt zurueck. "Nicht dokumentiert" heisst nicht
    // "unwichtig" - eher im Gegenteil.
    $html = Livewire::test(ObjektListe::class, ['typ' => 'aduser', 'customer' => $customer])->html();
    expect(str_contains($html, 'opacity-60'))->toBeFalse();
});

test('Name, Benutzername und Adresse gehoeren beim Demo-Benutzer zusammen', function () {
    $customer = Customer::factory()->create();
    ADUser::factory(30)->beiFirma('beispiel.de')->create(['customer_id' => $customer->id]);

    foreach (ADUser::where('customer_id', $customer->id)->get() as $benutzer) {
        $erwartet = Str::slug($benutzer->firstName).'.'.Str::slug($benutzer->lastName);

        // Vorher wuerfelte die Factory drei Werte unabhaengig voneinander -
        // in einer Zeile standen dann drei verschiedene Personen.
        expect($benutzer->username)->toBe($erwartet);

        if ($benutzer->email !== null) {
            expect($benutzer->email)->toBe($erwartet.'@beispiel.de');
        }
    }
});

test('die Demo-Daten zeigen alle drei Zustaende und beide Adressfaelle', function () {
    $this->seed(LocalDatabaseSeeder::class);

    $benutzer = ADUser::query();

    // Ohne ausdrueckliche Mischung im Seeder waere jeder fuenfte Datensatz
    // durchgehend aktiv gewesen - der gesperrte Fall also unsichtbar.
    expect((clone $benutzer)->where('enabled', true)->exists())->toBeTrue();
    expect((clone $benutzer)->where('enabled', false)->exists())->toBeTrue();
    expect((clone $benutzer)->whereNull('enabled')->exists())->toBeTrue();

    // Und beide Faelle bei der Adresse: Menschen haben eine, Dienstkonten nicht.
    expect((clone $benutzer)->whereNotNull('email')->exists())->toBeTrue();
    expect((clone $benutzer)->whereNull('email')->exists())->toBeTrue();
})->group('langsam');
