<?php

use App\Models\Customer;
use App\Models\NAS;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

test('NAS-Passwort wird verschlüsselt gespeichert und korrekt entschlüsselt', function () {
    $this->actingAs(userWithPermissions(['nas_create']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    imModal('nas', $customer, [
        'site_id' => $site->id,
        'name' => 'NAS1',
        'ip1' => '10.0.0.1',
        'username' => 'admin',
        'password' => 'geheim123',
    ])->assertHasNoErrors();

    // Roher DB-Wert ist verschlüsselt (kein Klartext)
    $raw = DB::table('nas')->first();
    expect($raw->password)->not->toBe('geheim123');

    // Über das Model wird korrekt entschlüsselt
    expect(NAS::first()->password)->toBe('geheim123');
});
