<?php

use App\Models\Customer;
use App\Models\LoginGeneral;
use Illuminate\Support\Facades\DB;

test('Login-Passwort wird verschlüsselt gespeichert und korrekt entschlüsselt', function () {
    $this->actingAs(userWithPermissions(['logingeneral_create']));

    $customer = Customer::factory()->create();

    imModal('logingeneral', $customer, [
        'name' => 'NAS1 Admin',
        'username' => 'admin',
        'password' => 'geheim123',
    ])->assertHasNoErrors();

    // Roher DB-Wert ist verschlüsselt (kein Klartext)
    $raw = DB::table('login_generals')->first();
    expect($raw->password)->not->toBe('geheim123');

    // Über das Model wird korrekt entschlüsselt
    expect(LoginGeneral::first()->password)->toBe('geheim123');
});
