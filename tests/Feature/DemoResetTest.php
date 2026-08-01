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
