<?php

use App\Models\Certificate;
use App\Models\Customer;

test('Zertifikat lässt sich anlegen', function () {
    $this->actingAs(userWithPermissions(['certificate_create']));
    $customer = Customer::factory()->create();

    imModal('certificate', $customer, [
        'name' => 'Wildcard *.kunde.de',
        'common_name' => '*.kunde.de',
        'issuer' => "Let's Encrypt",
        'type' => 'Wildcard',
        'expiry_date' => now()->addWeeks(2)->toDateString(),
    ])->assertHasNoErrors();

    expect(Certificate::where('customer_id', $customer->id)->count())->toBe(1);
});

test('ungültige Anlage ohne Bezeichnung wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['certificate_create']));
    $customer = Customer::factory()->create();

    imModal('certificate', $customer, ['name' => ''])
        ->assertHasErrors('form.name');

    expect(Certificate::where('customer_id', $customer->id)->count())->toBe(0);
});

test('ohne Recht kein Zugriff auf die Zertifikatsliste', function () {
    $this->actingAs(userWithPermissions([])); // kein certificate_viewAny
    $customer = Customer::factory()->create();

    $this->get("/{$customer->slug}/certificate")->assertForbidden();
});

test('bald ablaufendes Zertifikat erscheint auf dem Kunden-Dashboard', function () {
    $this->actingAs(userWithPermissions(['certificate_viewAny']));
    $customer = Customer::factory()->create();

    Certificate::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Ablaufendes Zertifikat',
        'expiry_date' => now()->addDays(5)->toDateString(),
    ]);
    // Ein weit entferntes Zertifikat darf nicht in der Warnliste auftauchen
    Certificate::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Fernes Zertifikat',
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    $response = $this->get("/{$customer->slug}");
    $response->assertStatus(200);
    $response->assertSee('Ablaufende Zertifikate');
    $response->assertSee('Ablaufendes Zertifikat');
});
