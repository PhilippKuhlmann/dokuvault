<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;
use Illuminate\Support\Facades\Route;

/**
 * Die Liste in config('custom.agenten') traegt beides: den Download im
 * AgentTokenController und die Reiter auf der Agent-Seite. Ein Endpunkt ohne
 * Eintrag waere ein Agent, dessen Script niemand bekommt; ein Eintrag ohne
 * Endpunkt ein Script, das ins Leere meldet. Beides faellt sonst niemandem auf.
 */
function agentEndpunkte(): array
{
    return collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('agent', $route->gatherMiddleware(), true))
        ->map(fn ($route) => str($route->uri())->afterLast('/')->toString())
        ->values()
        ->all();
}

test('zu jedem Agent-Endpunkt gibt es einen Eintrag in der Liste', function () {
    $eingetragen = collect(config('custom.agenten'))->pluck('endpunkt')->all();

    expect(agentEndpunkte())->not->toBeEmpty();

    foreach (agentEndpunkte() as $endpunkt) {
        expect($eingetragen)->toContain($endpunkt);
    }
});

test('zu jedem Eintrag in der Liste gibt es einen Endpunkt', function () {
    $endpunkte = agentEndpunkte();

    foreach (config('custom.agenten') as $schluessel => $agent) {
        expect(in_array($agent['endpunkt'], $endpunkte, true))
            ->toBeTrue("Agent '$schluessel' hat keine Route unter middleware('agent').");
    }
});

test('zu jedem Eintrag gibt es eine Scriptdatei mit beiden Platzhaltern', function () {
    foreach (config('custom.agenten') as $schluessel => $agent) {
        $pfad = resource_path('agents/'.$agent['skript']);

        expect(file_exists($pfad))->toBeTrue("Script fuer '$schluessel' fehlt: $pfad");

        // Ohne die Platzhalter laedt der Nutzer ein Script herunter, das weder
        // weiss, wohin es melden soll, noch mit welchem Token.
        $inhalt = file_get_contents($pfad);
        expect(str_contains($inhalt, '__API_URL__'))->toBeTrue("In '$schluessel' fehlt __API_URL__.");
        expect(str_contains($inhalt, '__AGENT_TOKEN__'))->toBeTrue("In '$schluessel' fehlt __AGENT_TOKEN__.");
    }
});

test('jeder Eintrag beschreibt, was das Script tut und wie man es aufruft', function () {
    foreach (config('custom.agenten') as $schluessel => $agent) {
        foreach (['name', 'skript', 'endpunkt', 'datei', 'ausfuehren_auf', 'aufruf', 'erreichbar_von', 'ueberschreiben'] as $feld) {
            expect($agent[$feld] ?? null)->toBeString("Agent '$schluessel': '$feld' fehlt.")
                ->not->toBe('');
        }

        expect($agent['zugangsdaten'] ?? null)->toBeBool("Agent '$schluessel': 'zugangsdaten' fehlt.");
        expect($agent['macht'] ?? [])->not->toBeEmpty("Agent '$schluessel' erklaert nicht, was das Script tut.");
    }
});

test('das erzeugte Token liefert zu jedem Agenten ein fertiges Script', function () {
    $this->actingAs(userWithPermissions(['see_hidden']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $this->post(route('agent.store', $customer), ['name' => 'Prüflauf', 'site_id' => $site->id])
        ->assertRedirect(route('agent.index', $customer));

    $token = session('newToken');
    $skripte = session('agentSkripte');

    expect($skripte)->toHaveCount(count(config('custom.agenten')));

    foreach (config('custom.agenten') as $schluessel => $agent) {
        $skript = $skripte[$schluessel];

        // Eingesetzt, nicht mehr Platzhalter.
        expect($skript)->toContain($token)
            ->and($skript)->toContain(url('/api/agent/'.$agent['endpunkt']))
            ->and($skript)->not->toContain('__API_URL__')
            ->and($skript)->not->toContain('__AGENT_TOKEN__');
    }

    // Der Token wurde tatsaechlich angelegt und gehoert diesem Kunden.
    expect(AgentToken::where('customer_id', $customer->id)->count())->toBe(1);
});
