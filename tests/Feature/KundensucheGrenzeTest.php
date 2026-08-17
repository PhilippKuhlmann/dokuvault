<?php

use App\Livewire\SearchCustomer;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Die Kundensuche war die einzige Stelle, die mit dem Bestand linear
 * mitwuchs: Sie holte alle Treffer. Bei 558 Kunden und einem Buchstaben als
 * Suchbegriff waren das 70 KB Antwort - bei einigen Tausend entsprechend mehr.
 */
test('die Kundensuche zeigt hoechstens fuenfzig Treffer und sagt es', function () {
    $this->actingAs(userWithPermissions([]));

    Customer::factory()->count(60)->create(['name' => fn () => 'Testkunde '.fake()->unique()->numberBetween(1, 9999)]);

    // Entscheidend ist, was die Datenbank liefert, nicht was die Liste zeigt:
    // Ein take() in PHP begrenzt die Anzeige auch dann, wenn vorher alle
    // Kunden geladen wurden - genau das war das Problem.
    $abfragen = [];
    DB::listen(function ($a) use (&$abfragen) {
        $abfragen[] = $a->sql;
    });

    Livewire::test(SearchCustomer::class)
        ->set('search', 'Testkunde')
        ->assertViewHas('customers', fn ($c) => $c->count() === 50)
        ->assertViewHas('weitere', fn ($w) => $w > 0)
        // Ohne den Hinweis sieht es aus, als gaebe es keine weiteren Kunden.
        ->assertSee('Weitere Treffer vorhanden');

    $kundenAbfrage = collect($abfragen)->first(fn ($sql) => str_contains($sql, 'customers') && str_contains($sql, 'like'));

    expect($kundenAbfrage)->toContain('limit 51');
});

test('bei wenigen Treffern erscheint kein Hinweis', function () {
    $this->actingAs(userWithPermissions([]));

    Customer::factory()->count(3)->create(['name' => fn () => 'Kleinkunde '.fake()->unique()->numberBetween(1, 999)]);

    Livewire::test(SearchCustomer::class)
        ->set('search', 'Kleinkunde')
        ->assertViewHas('weitere', 0)
        ->assertDontSee('Weitere Treffer vorhanden');
});
