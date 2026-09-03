<?php

use App\Livewire\AdminFristen;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\PdfExport;
use App\Models\Role;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function admin(): User
{
    $rolle = Role::factory()->create(['id' => Role::IS_ADMIN]);

    return User::factory()->create(['role_id' => $rolle->id]);
}

test('ohne Einstellung gilt die Konfiguration', function () {
    expect(Setting::fristVertraege())->toBe(config('custom.fristen.vertraege_tage'))
        ->and(Setting::fristGarantie())->toBe(config('custom.fristen.garantie_tage'))
        ->and(Setting::fristEol())->toBe(config('custom.fristen.eol_tage'))
        ->and(Setting::pdfStunden())->toBe(config('custom.fristen.pdf_stunden'));
});

test('eine eigene Frist verdrängt die Konfiguration', function () {
    Setting::setzen(Setting::FRIST_VERTRAEGE, 120);

    expect(Setting::fristVertraege())->toBe(120);
});

/*
|--------------------------------------------------------------------------
| Die Wirkung, nicht die Ablage
|--------------------------------------------------------------------------
|
| Dass eine Zahl in der Tabelle steht, ist noch keine Frist. Diese Tests
| stellen jeweils etwas ein und sehen nach, ob sich die Anzeige aendert.
|
*/

test('die Vorwarnzeit entscheidet, was auf dem Kundendashboard steht', function () {
    $customer = Customer::factory()->create();
    Certificate::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'wildcard.example.test',
        'expiry_date' => now()->addDays(90),
    ]);

    $this->actingAs(admin());

    // 60 Tage Vorwarnzeit: 90 Tage sind zu weit weg.
    $this->get(route('customer.dashboard', $customer))->assertDontSee('wildcard.example.test');

    Setting::setzen(Setting::FRIST_VERTRAEGE, 120);

    $this->get(route('customer.dashboard', $customer))->assertSee('wildcard.example.test');
});

test('die Vorwarnzeit entscheidet, was in der Übersicht über alle Kunden steht', function () {
    $customer = Customer::factory()->create();
    Certificate::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'global.example.test',
        'expiry_date' => now()->addDays(90),
    ]);

    $this->actingAs(admin());

    $this->get('/admin')->assertDontSee('global.example.test');

    Setting::setzen(Setting::FRIST_VERTRAEGE, 120);

    $this->get('/admin')->assertSee('global.example.test');
});

test('die Garantiefrist hat ihre eigene Zahl', function () {
    // Getrennt von den Vertraegen, weil es die andere Arbeit ist: Eine Lizenz
    // verlaengert man, ein Geraet ohne Garantie muss ersetzt werden.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-GARANTIE', 'warranty_until' => now()->addDays(90),
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Debian 12'])->id,
    ]);

    $this->actingAs(admin());

    $this->get(route('customer.dashboard', $customer))->assertDontSee('SRV-GARANTIE');

    Setting::setzen(Setting::FRIST_GARANTIE, 120);

    $this->get(route('customer.dashboard', $customer))->assertSee('SRV-GARANTIE');

    // Und die Vertragsfrist hat sie nicht mitgezogen.
    expect(Setting::fristVertraege())->toBe(config('custom.fristen.vertraege_tage'));
});

test('die EOL-Frist entscheidet über das Abzeichen am Betriebssystem', function () {
    $os = new OperatingSystem(['eol_date' => now()->addDays(300)]);

    expect($os->laeuftAus())->toBeFalse();

    Setting::setzen(Setting::FRIST_EOL, 365);

    expect($os->laeuftAus())->toBeTrue();
});

test('Abzeichen und EOL-Liste folgen derselben Frist', function () {
    // Die Invariante: Beide Zahlen standen einzeln im Code, mit dem Kommentar,
    // es sei dieselbe Schwelle. Eine Einstellung muss beide bewegen - sonst
    // zeigt die Liste ein System, das kein Abzeichen traegt, oder umgekehrt.
    $os = OperatingSystem::factory()->create([
        'name' => 'Ubuntu 20.04 LTS',
        'eol_date' => now()->addDays(300),
    ]);

    // Die Liste zeigt Geraete, nicht Systeme: Ein EOL-System, auf dem nichts
    // laeuft, ist kein Problem und steht deshalb nicht darin.
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    Server::create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'SRV-BALD-EOL', 'operating_system_id' => $os->id,
    ]);

    $this->actingAs(admin());

    $this->get(route('admin.eol.index'))->assertDontSee('SRV-BALD-EOL');

    Setting::setzen(Setting::FRIST_EOL, 365);

    $this->get(route('admin.eol.index'))->assertSee('SRV-BALD-EOL');
});

test('die PDF-Frist entscheidet, was aufgeräumt wird', function () {
    $customer = Customer::factory()->create();
    $nutzer = admin();

    $auftrag = PdfExport::create([
        'customer_id' => $customer->id,
        'user_id' => $nutzer->id,
        'status' => PdfExport::FERTIG,
    ]);
    $auftrag->forceFill(['created_at' => now()->subHours(10)])->save();

    // 24 Stunden Aufbewahrung: zehn Stunden alt bleibt liegen.
    $this->artisan('pdf:aufraeumen');
    expect(PdfExport::find($auftrag->id))->not->toBeNull();

    Setting::setzen(Setting::PDF_STUNDEN, 6);

    $this->artisan('pdf:aufraeumen');
    expect(PdfExport::find($auftrag->id))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Die Einstellungsseite
|--------------------------------------------------------------------------
*/

test('die Seite speichert beim Ändern, ohne Knopf', function () {
    $this->actingAs(admin());

    Livewire::test(AdminFristen::class)
        ->set('vertraege', 90)
        ->set('garantie', 45)
        ->set('eol', 365)
        ->set('pdfStunden', 4)
        ->assertHasNoErrors();

    expect(Setting::fristVertraege())->toBe(90)
        ->and(Setting::fristGarantie())->toBe(45)
        ->and(Setting::fristEol())->toBe(365)
        ->and(Setting::pdfStunden())->toBe(4);
});

test('null Tage wäre keine Frist, sondern eine abgeschaltete Warnung', function () {
    $this->actingAs(admin());

    Livewire::test(AdminFristen::class)
        ->set('vertraege', 0)
        ->assertHasErrors(['vertraege']);

    // Und die bisherige Frist steht noch.
    expect(Setting::fristVertraege())->toBe(config('custom.fristen.vertraege_tage'));
});

test('ein vertippter Wert wird abgewiesen', function () {
    $this->actingAs(admin());

    // Wer 60 meint und 6000 tippt, warnt ab sofort vor allem.
    Livewire::test(AdminFristen::class)
        ->set('vertraege', 6000)
        ->assertHasErrors(['vertraege']);
});

test('eine PDF-Frist unter einer Stunde wäre eine Zahl ohne Wirkung', function () {
    $this->actingAs(admin());

    // Aufgeraeumt wird stuendlich - kuerzer geht nicht, egal was dasteht.
    Livewire::test(AdminFristen::class)
        ->set('pdfStunden', 0)
        ->assertHasErrors(['pdfStunden']);
});

test('ohne das Recht kommt niemand auf die Seite', function () {
    $this->actingAs(userWithPermissions([]));

    $this->get(route('admin.fristen.index'))->assertForbidden();
});
