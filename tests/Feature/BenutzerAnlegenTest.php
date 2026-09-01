<?php

use App\Http\Requests\UserRequest;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;

/**
 * Das Formular des Adminbereichs, ueber das Benutzer entstehen. Wer hier eine
 * Kundennummer setzt, entscheidet damit, ob jemand nur einen Kunden sieht oder
 * alle - es gibt keine zweite Stelle, an der das haengt.
 */
function alsBenutzerverwalter(): User
{
    $nutzer = userWithPermissions(['admin_user']);
    test()->actingAs($nutzer);

    return $nutzer;
}

function benutzerdaten(array $abweichend = []): array
{
    return array_merge([
        'name' => 'Neue Kollegin',
        'username' => 'neue.kollegin',
        'password' => 'Ein-Gutes-Kennwort-2026',
        'email' => null,
        'role_id' => Role::first()->id,
        'customer_id' => null,
    ], $abweichend);
}

test('ein Benutzer ohne Kunde lässt sich anlegen - das ist der Techniker', function () {
    alsBenutzerverwalter();

    $this->post(route('admin.user.store'), benutzerdaten())
        ->assertRedirect(route('admin.user.index'));

    expect(User::where('username', 'neue.kollegin')->first()->customer_id)->toBeNull();
});

test('ein Benutzer mit Kunde lässt sich anlegen', function () {
    alsBenutzerverwalter();
    $kunde = Customer::factory()->create();

    $this->post(route('admin.user.store'), benutzerdaten(['customer_id' => $kunde->id]))
        ->assertRedirect(route('admin.user.index'));

    expect(User::where('username', 'neue.kollegin')->first()->customer_id)->toBe($kunde->id);
});

test('eine Kundennummer, die es nicht gibt, wird abgewiesen', function () {
    alsBenutzerverwalter();

    $this->post(route('admin.user.store'), benutzerdaten(['customer_id' => 999999]))
        ->assertSessionHasErrors('customer_id');

    expect(User::where('username', 'neue.kollegin')->exists())->toBeFalse();
});

test('ein Kunde im Papierkorb zählt nicht', function () {
    alsBenutzerverwalter();
    $kunde = Customer::factory()->create();
    $kunde->delete();

    $this->post(route('admin.user.store'), benutzerdaten(['customer_id' => $kunde->id]))
        ->assertSessionHasErrors('customer_id');
});

test('eine Rolle, die es nicht gibt, wird abgewiesen', function () {
    alsBenutzerverwalter();

    $this->post(route('admin.user.store'), benutzerdaten(['role_id' => 999999]))
        ->assertSessionHasErrors('role_id');
});

test('kurze Kennwörter werden abgewiesen', function () {
    alsBenutzerverwalter();

    $this->post(route('admin.user.store'), benutzerdaten(['password' => 'kurz12']))
        ->assertSessionHasErrors('password');
});

test('keine Regel haengt mehr an festen Rollennummern', function () {
    // required_if:role_id,98,99 stammte aus einer Zeit vor dem Rollen-
    // Adminbereich. Rollen bekommen dort fortlaufende Nummern; 98 und 99 gibt
    // es in keiner Installation, die Regel war nie wahr. Ob jemand Kundennutzer
    // ist, haengt an der customer_id, nicht an seiner Rolle.
    $regeln = json_encode((new UserRequest)->rules());

    expect($regeln)->not->toContain('98')
        ->and($regeln)->not->toContain('required_if');
});

test('die Auswahl sagt, was "kein Kunde" bedeutet', function () {
    // "Kein Kunde" liest sich harmlos. Wer es aus Versehen stehen laesst, legt
    // einen Benutzer an, der die Daten aller Kunden sieht.
    alsBenutzerverwalter();

    $this->get(route('admin.user.create'))
        ->assertSee('Kein Kunde – sieht alle Kunden');
});
