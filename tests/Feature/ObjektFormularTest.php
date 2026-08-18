<?php

use App\Livewire\ObjektFormular;
use App\Livewire\ObjektListe;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('die Feldliste deckt ab, was der Request erlaubt', function () {
    // Der eigentliche Schutz dieses Umbaus: Faellt ein Feld aus der Definition
    // heraus, laesst es sich im Modal nicht mehr ausfuellen - ohne Fehlermeldung.
    $luecken = [];

    foreach (config('forms') as $typ => $einstellung) {
        $request = new $einstellung['request'];
        $definiert = array_column($einstellung['felder'], 'name');

        foreach (array_keys($request->rules()) as $feld) {
            if (! in_array($feld, $definiert, true)) {
                $luecken[] = "$typ.$feld";
            }
        }
    }

    expect($luecken)->toBe([], 'Im Modal fehlen Felder, die der Request erlaubt: '.implode(', ', $luecken));
});

test('anlegen im Modal legt an und meldet es der Liste', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny', 'domain_create']));

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->assertSet('offen', true)
        ->set('form.name', 'beispiel.de')
        ->set('form.registrar', 'Hetzner')
        ->call('speichern')
        ->assertSet('offen', false)
        ->assertDispatched('objekt-gespeichert', typ: 'domain');

    $domain = Domain::where('name', 'beispiel.de')->sole();
    expect($domain->customer_id)->toBe($customer->id);
    expect($domain->registrar)->toBe('Hetzner');
});

test('bearbeiten laedt die Werte und speichert sie zurueck', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny', 'domain_update']));

    $domain = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'alt.de']);

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('bearbeiten', 'domain', $domain->id)
        ->assertSet('form.name', 'alt.de')
        ->set('form.name', 'neu.de')
        ->call('speichern');

    expect($domain->fresh()->name)->toBe('neu.de');
});

test('Neu nach Bearbeiten legt an, statt den alten Eintrag zu aendern', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny', 'domain_create', 'domain_update']));

    $domain = Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'bestand.de']);

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('bearbeiten', 'domain', $domain->id)
        ->call('neu')
        ->assertSet('bearbeiteId', null)
        ->assertSet('form.name', '')
        ->set('form.name', 'zweite.de')
        ->call('speichern');

    expect($domain->fresh()->name)->toBe('bestand.de');
    expect(Domain::where('name', 'zweite.de')->exists())->toBeTrue();
});

test('eine fremde Domain laesst sich nicht bearbeiten', function () {
    $customer = Customer::factory()->create();
    $fremd = Customer::factory()->create();
    $fremdeDomain = Domain::factory()->create(['customer_id' => $fremd->id]);

    $this->actingAs(userWithPermissions(['domain_update']));

    // Die Id kommt vom Browser - sie wird deshalb immer ueber den Kunden
    // geladen, und eine fremde laeuft ins Leere statt fremde Daten zu oeffnen.
    expect(fn () => Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('bearbeiten', 'domain', $fremdeDomain->id))
        ->toThrow(ModelNotFoundException::class);
});

test('ohne Recht kein Anlegen', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny']));

    Livewire::test(ObjektFormular::class, ['typ' => 'domain', 'customer' => $customer])
        ->call('neu')
        ->assertForbidden();
});

test('die Liste sucht und zeigt den Anlegen-Knopf', function () {
    $customer = Customer::factory()->create();
    $this->actingAs(userWithPermissions(['domain_viewAny', 'domain_create']));

    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'treffer.de']);
    Domain::factory()->create(['customer_id' => $customer->id, 'name' => 'anderes.de']);

    Livewire::test(ObjektListe::class, ['typ' => 'domain', 'customer' => $customer])
        ->assertSee('treffer.de')
        ->assertSee('anderes.de')
        ->set('search', 'treffer')
        ->assertSee('treffer.de')
        ->assertDontSee('anderes.de');
});

test('jede Liste aus config/forms ist auf das Modal umgestellt', function () {
    // Bleibt eine Liste auf der alten Seite, faellt das sonst erst auf, wenn
    // jemand dort auf "Neu" klickt und auf einer Seite landet statt im Modal.
    $offen = [];

    foreach (array_keys(config('forms')) as $typ) {
        $index = resource_path("views/$typ/index.blade.php");

        if (! str_contains(file_get_contents($index), 'livewire:objekt-liste')) {
            $offen[] = $typ;
        }

        // Und die Karte bzw. Tabellenzeile muss es geben, sonst rendert die
        // Liste ins Leere.
        if (! view()->exists($typ.'._karte') && ! view()->exists($typ.'._zeile')) {
            $offen[] = $typ.' (ohne Teilstück)';
        }
    }

    expect($offen)->toBe([], 'Noch nicht umgestellt: '.implode(', ', $offen));
});

test('jede umgestellte Liste zeigt einen Bearbeiten-Knopf', function () {
    // Der Fall, der mir durchgerutscht ist: x-table.datarow kannte nur editUrl.
    // Ein unbekanntes Attribut wirft nichts - der Stift fehlte einfach.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $ohneKnopf = [];

    foreach (config('forms') as $typ => $einstellung) {
        $this->actingAs(userWithPermissions([$typ.'_viewAny', $typ.'_update']));

        $klasse = $einstellung['model'];
        $werte = ['customer_id' => $customer->id];

        // Einige Tabellen verlangen einen Standort.
        if (Schema::hasColumn((new $klasse)->getTable(), 'site_id')) {
            $werte['site_id'] = $site->id;
        }

        $klasse::factory()->create($werte);

        // Entities aufloesen: Blade schreibt die Anfuehrungszeichen im
        // wire:click als &#039;, die Suche nach dem Ausdruck ginge sonst leer aus.
        $html = html_entity_decode(
            Livewire::test(ObjektListe::class, ['typ' => $typ, 'customer' => $customer])->html()
        );

        // Auf wire:click pruefen, nicht auf den blossen Text: Ein unbekanntes
        // Attribut reicht Blade unveraendert ins Element durch - der Name des
        // Ereignisses stand also auch dann im HTML, wenn gar kein Knopf da war.
        if (! str_contains($html, 'wire:click="$dispatch(\'objekt-bearbeiten\'')) {
            $ohneKnopf[] = $typ;
        }
    }

    expect($ohneKnopf)->toBe([], 'Ohne Bearbeiten-Knopf: '.implode(', ', $ohneKnopf));
});
