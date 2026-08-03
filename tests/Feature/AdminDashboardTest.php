<?php

use App\Models\Certificate;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;

test('Admin-Dashboard zeigt Statistik-Kacheln', function () {
    $adminRole = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $this->actingAs($admin);

    $this->get('/admin')
        ->assertStatus(200)
        ->assertSee('Administration')
        ->assertSee('Benutzer')
        ->assertSee('Kunden')
        ->assertSee('Rollen')
        ->assertSee('Dokumentiertes Inventar')
        ->assertSee('Läuft demnächst ab')
        ->assertSee('Letzte Aktivitäten')
        ->assertSee('Top-Kunden nach Geräten')
        ->assertSee('Aktivität (14 Tage)');
});

test('Admin-Dashboard zeigt global ablaufende Zertifikate/Lizenzen', function () {
    $adminRole = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $this->actingAs($admin);

    $customer = Customer::factory()->create(['name' => 'Beispiel GmbH']);
    Certificate::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Ablaufendes Wildcard',
        'expiry_date' => now()->addDays(10)->toDateString(),
    ]);

    $this->get('/admin')
        ->assertStatus(200)
        ->assertSee('Ablaufendes Wildcard')
        ->assertSee('Beispiel GmbH');
});

test('Nicht-Admin wird vom Admin-Dashboard abgewiesen', function () {
    $this->actingAs(userWithPermissions([]));

    $this->get('/admin')->assertStatus(403);
});
