<?php

use App\Models\ADDomain;
use App\Models\Customer;

test('die AD-Domaenen-Factory fuellt die Pflichtfelder', function () {
    $customer = Customer::factory()->create();

    // Alle drei Spalten sind NOT NULL - vorher lieferte die Factory ein leeres
    // Array und jedes create() ohne eigene Werte brach ab.
    $domaenen = ADDomain::factory()->count(3)->create(['customer_id' => $customer->id]);

    foreach ($domaenen as $domaene) {
        expect($domaene->domain)->toStartWith('ad.')->toEndWith('.de');
        expect($domaene->netbios)->toBe(mb_strtoupper($domaene->netbios));
        expect(mb_strlen($domaene->netbios))->toBeLessThanOrEqual(15);
        expect($domaene->dsrmpassword)->not->toBeEmpty();
    }

    // Verschiedene Domaenen, sonst kollidieren mehrere Kunden im Seeder.
    expect($domaenen->pluck('domain')->unique())->toHaveCount(3);
});
