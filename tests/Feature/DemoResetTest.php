<?php

use App\Models\Customer;

test('demo:reset verweigert den Dienst ohne DEMO_MODE', function () {
    config(['app.demo' => false]);
    Customer::factory()->create(['name' => 'Wichtiger Kunde']);

    $this->artisan('demo:reset')
        ->expectsOutputToContain('DEMO_MODE ist nicht aktiv')
        ->assertExitCode(1);

    // Der Befehl loescht die komplette Datenbank - auf einer echten
    // Installation waere ein versehentlicher Aufruf der Totalverlust.
    expect(Customer::where('name', 'Wichtiger Kunde')->exists())->toBeTrue();
});

test('der Demo-Hinweis erscheint nur bei aktivem DEMO_MODE', function () {
    config(['app.demo' => false]);
    $this->get('/login')->assertStatus(200)->assertDontSee('stündlich zurückgesetzt');

    config(['app.demo' => true]);
    $this->get('/login')->assertStatus(200)->assertSee('stündlich zurückgesetzt', false);
});

test('bricht der Neuaufbau ab, bleibt die Demo nicht im Wartungsmodus', function () {
    // Der echte Durchlauf ist hier nicht zu haben: migrate:fresh will
    // vacuum, und jeder Test laeuft in einer Transaktion. Geprueft wird
    // darum der Fall, fuer den das finally im Befehl da ist - der Neuaufbau
    // scheitert (hier an einer Verbindung, die es nicht gibt), und die Demo
    // darf trotzdem nicht auf 503 stehenbleiben.
    $echte = config('database.default');
    config(['app.demo' => true, 'database.default' => 'gibt-es-nicht']);

    try {
        expect(fn () => $this->artisan('demo:reset')->run())
            ->toThrow(InvalidArgumentException::class);
    } finally {
        // Zurueck auf die echte Verbindung, sonst scheitert das Aufraeumen
        // des Tests (die Transaktion liegt auf ihr).
        config(['database.default' => $echte]);
    }

    expect($this->app->isDownForMaintenance())->toBeFalse();
});
