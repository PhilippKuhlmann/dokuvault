<?php

use App\Models\ADUser;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Customer;

/**
 * Checklisten in der App: sehen, abhaken, freigeben.
 *
 * Abgehakt wird in App\Support\Checklistenpflege, derselben Klasse, die die
 * Seite im Browser ruft - hier geht es um die Rechte und darum, was ueber
 * die Leitung geht.
 */
function checklistenToken(array $rechte, ?Customer $kunde = null): string
{
    $nutzer = userWithPermissions($rechte);
    $nutzer->forceFill(['customer_id' => $kunde?->id])->save();

    return $nutzer->fresh()->createToken('App Test', ['mobil'], now()->addDay())->plainTextToken;
}

function checkliste(Customer $kunde): Checklist
{
    $liste = Checklist::create([
        'customer_id' => $kunde->id,
        'type' => 'onboarding',
        'first_name' => 'Sophie',
        'last_name' => 'Krämer',
        'position' => 'Buchhaltung',
        'reference_date' => '2026-11-16',
    ]);

    ChecklistItem::create([
        'checklist_id' => $liste->id, 'section' => 'Konto', 'title' => 'AD-Konto anlegen',
        'assignee' => 'it', 'sort' => 1,
    ]);
    ChecklistItem::create([
        'checklist_id' => $liste->id, 'section' => 'Laufwerke', 'title' => 'Laufwerk S:',
        'assignee' => 'it', 'sort' => 2, 'needs_approval' => true,
    ]);

    return $liste;
}

test('die Liste nennt Offene mit Fortschritt und wartenden Freigaben', function () {
    $kunde = Customer::factory()->create();
    $liste = checkliste($kunde);

    $antwort = test()->withToken(checklistenToken(['checklist_manage']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/checklists")
        ->assertOk();

    expect($antwort->json('offen.0.name'))->toBe('Sophie Krämer');
    expect($antwort->json('offen.0.art'))->toBe('onboarding');
    expect($antwort->json('offen.0.punkte'))->toBe(2);
    expect($antwort->json('offen.0.erledigt'))->toBe(0);
    expect($antwort->json('offen.0.wartet_auf_freigabe'))->toBe(1);
    expect($antwort->json('darf_abhaken'))->toBeTrue();
    expect($antwort->json('darf_freigeben'))->toBeFalse();
});

test('eine Checkliste zeigt ihre Punkte nach Abschnitten', function () {
    $kunde = Customer::factory()->create();
    $liste = checkliste($kunde);

    $antwort = test()->withToken(checklistenToken(['checklist_manage']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}")
        ->assertOk();

    expect(collect($antwort->json('abschnitte'))->pluck('titel')->all())->toBe(['Konto', 'Laufwerke']);
    expect($antwort->json('abschnitte.1.punkte.0.braucht_freigabe'))->toBeTrue();
    expect($antwort->json('abschnitte.1.punkte.0.freigegeben'))->toBeFalse();
});

test('ohne jedes Checklisten-Recht bleibt die Liste zu', function () {
    $kunde = Customer::factory()->create();

    test()->withToken(checklistenToken(['server_viewAny']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/checklists")
        ->assertForbidden();
});

test('abhaken und den Haken wieder wegnehmen', function () {
    $kunde = Customer::factory()->create();
    $liste = checkliste($kunde);
    $punkt = $liste->items()->where('section', 'Konto')->firstOrFail();
    $token = checklistenToken(['checklist_manage']);
    $pfad = "/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/items/{$punkt->id}/toggle";

    expect(test()->withToken($token)->postJson($pfad)->assertOk()->json('erledigt'))->toBeTrue();
    expect($punkt->fresh()->done_at)->not->toBeNull();

    expect(test()->withToken($token)->postJson($pfad)->assertOk()->json('erledigt'))->toBeFalse();
    expect($punkt->fresh()->done_at)->toBeNull();
});

/** Ein Punkt vor der Freigabe ist kein Fehler der App, sondern eine Reihenfolge. */
test('ein Punkt ohne Freigabe lässt sich nicht abhaken', function () {
    $kunde = Customer::factory()->create();
    $liste = checkliste($kunde);
    $punkt = $liste->items()->where('needs_approval', true)->firstOrFail();

    test()->withToken(checklistenToken(['checklist_manage']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/items/{$punkt->id}/toggle")
        ->assertStatus(409);

    expect($punkt->fresh()->done_at)->toBeNull();
});

/*
 * Je ein Nutzer pro Test: Innerhalb eines Tests bleibt der zuerst
 * angemeldete im Guard stehen, ein zweites withToken() wechselt ihn nicht
 * mehr. Das hat hier einmal eine 403 verschluckt.
 */
test('wer nur abhaken darf, darf nicht freigeben', function () {
    $kunde = Customer::factory()->create();
    $liste = checkliste($kunde);
    $punkt = $liste->items()->where('needs_approval', true)->firstOrFail();

    test()->withToken(checklistenToken(['checklist_manage']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/items/{$punkt->id}/approve")
        ->assertForbidden();

    expect($punkt->fresh()->approved_at)->toBeNull();
});

test('wer nur freigeben darf, darf nicht abhaken', function () {
    $kunde = Customer::factory()->create();
    $liste = checkliste($kunde);
    $punkt = $liste->items()->where('section', 'Konto')->firstOrFail();

    test()->withToken(checklistenToken(['checklist_approve']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/items/{$punkt->id}/toggle")
        ->assertForbidden();

    expect($punkt->fresh()->done_at)->toBeNull();
});

test('freigeben öffnet den Punkt fürs Abhaken', function () {
    $kunde = Customer::factory()->create();
    $liste = checkliste($kunde);
    $punkt = $liste->items()->where('needs_approval', true)->firstOrFail();

    test()->withToken(checklistenToken(['checklist_approve']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/items/{$punkt->id}/approve")
        ->assertOk();

    expect($punkt->fresh()->approved_at)->not->toBeNull();
    expect($punkt->fresh()->awaitsApproval())->toBeFalse();
});

test('eine Checkliste eines anderen Kunden ist ein 404', function () {
    $eigener = Customer::factory()->create();
    $fremder = Customer::factory()->create();
    $liste = checkliste($fremder);

    test()->withToken(checklistenToken(['checklist_manage']))
        ->getJson("/api/mobile/customers/{$eigener->slug}/checklists/{$liste->id}")
        ->assertNotFound();
});

/** Ist alles abgehakt, gilt die Liste als erledigt - wie im Browser. */
test('mit dem letzten Haken ist die Liste fertig', function () {
    $kunde = Customer::factory()->create();
    $liste = checkliste($kunde);
    $liste->items()->where('needs_approval', true)->delete();
    $punkt = $liste->items()->firstOrFail();

    $antwort = test()->withToken(checklistenToken(['checklist_manage']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/items/{$punkt->id}/toggle")
        ->assertOk();

    expect($antwort->json('fertig'))->toBeTrue();
    expect($liste->fresh()->completed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Beantragen
|--------------------------------------------------------------------------
*/

test('das Formular nennt Laufwerke, Programme und AD-Benutzer', function () {
    $kunde = checklistCustomer();
    onboardingShare($kunde, ['name' => 'Gemeinsam']);
    checklistOption($kunde, ['name' => 'Nextcloud']);
    ADUser::factory()->create([
        'customer_id' => $kunde->id, 'username' => 'max.muster',
        'firstName' => 'Max', 'lastName' => 'Muster', 'enabled' => true,
    ]);

    $antwort = test()->withToken(checklistenToken(['checklist_manage']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/checklists/form")
        ->assertOk();

    expect($antwort->json('laufwerke.0.name'))->toBe('Gemeinsam');
    // Beide Stufen, weil Lese- und Schreibgruppe hinterlegt sind.
    expect(collect($antwort->json('laufwerke.0.stufen'))->pluck('stufe')->all())->toBe(['r', 'rw']);
    expect($antwort->json('programme.0.name'))->toBe('Nextcloud');
    expect($antwort->json('ad_benutzer.0.benutzername'))->toBe('max.muster');
    expect($antwort->json('darf_offboarding'))->toBeTrue();
});

/** Ein Offboarding anzustossen ist Sache der IT. */
test('wer nur melden darf, bekommt keine AD-Benutzer', function () {
    $kunde = checklistCustomer();
    ADUser::factory()->create(['customer_id' => $kunde->id, 'enabled' => true]);

    $antwort = test()->withToken(checklistenToken(['checklist_request']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/checklists/form")
        ->assertOk();

    expect($antwort->json('ad_benutzer'))->toBe([]);
    expect($antwort->json('darf_offboarding'))->toBeFalse();
});

test('ein Onboarding entsteht mit Benutzername aus dem Namen', function () {
    $kunde = checklistCustomer();
    $freigabe = onboardingShare($kunde, ['name' => 'Gemeinsam']);

    $antwort = test()->withToken(checklistenToken(['checklist_request']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/onboarding", [
            'first_name' => 'Sophie',
            'last_name' => 'Krämer',
            'reference_date' => '2026-11-16',
            'drives' => [$freigabe->id => 'rw'],
            'options' => [],
        ])
        ->assertCreated();

    expect($antwort->json('checkliste.name'))->toBe('Sophie Krämer');
    expect($antwort->json('checkliste.art'))->toBe('onboarding');

    $liste = Checklist::firstOrFail();
    expect($liste->username)->toBe('sophie.kraemer');
    expect($liste->items()->where('section', 'Laufwerke')->exists())->toBeTrue();
});

test('eine unbekannte Stufe wird abgewiesen', function () {
    $kunde = checklistCustomer();
    $freigabe = onboardingShare($kunde);

    test()->withToken(checklistenToken(['checklist_request']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/onboarding", [
            'first_name' => 'Sophie', 'last_name' => 'Krämer',
            'reference_date' => '2026-11-16',
            'drives' => [$freigabe->id => 'alles'],
        ])
        ->assertStatus(422);
});

test('ein Offboarding braucht das Verwaltungsrecht', function () {
    $kunde = checklistCustomer();
    $benutzer = ADUser::factory()->create(['customer_id' => $kunde->id, 'enabled' => true]);

    test()->withToken(checklistenToken(['checklist_request']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/offboarding", [
            'ad_user_id' => $benutzer->id,
            'reference_date' => '2026-11-30',
        ])
        ->assertForbidden();
});

test('mit dem Recht entsteht das Offboarding zum AD-Benutzer', function () {
    $kunde = checklistCustomer();
    $benutzer = ADUser::factory()->create([
        'customer_id' => $kunde->id, 'username' => 'jens.ott',
        'firstName' => 'Jens', 'lastName' => 'Ott', 'enabled' => true,
    ]);

    $antwort = test()->withToken(checklistenToken(['checklist_manage']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/offboarding", [
            'ad_user_id' => $benutzer->id,
            'reference_date' => '2026-11-30',
            'note' => 'Übergabe an Frau Meier',
        ])
        ->assertCreated();

    expect($antwort->json('checkliste.art'))->toBe('offboarding');
    expect($antwort->json('checkliste.name'))->toBe('Jens Ott');
});

/** Ein AD-Benutzer eines anderen Kunden ist nicht dieser. */
test('ein fremder AD-Benutzer ergibt 404', function () {
    $kunde = checklistCustomer();
    $fremder = ADUser::factory()->create([
        'customer_id' => Customer::factory()->create()->id, 'enabled' => true,
    ]);

    test()->withToken(checklistenToken(['checklist_manage']))
        ->postJson("/api/mobile/customers/{$kunde->slug}/checklists/offboarding", [
            'ad_user_id' => $fremder->id,
            'reference_date' => '2026-11-30',
        ])
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| AD-Skript
|--------------------------------------------------------------------------
*/

test('das AD-Skript kommt heraus und gibt einen neuen Schlüssel aus', function () {
    $kunde = checklistCustomer();
    $liste = checkliste($kunde);
    $liste->update(['username' => 'sophie.kraemer']);

    $antwort = test()->withToken(checklistenToken(['checklist_manage']))
        ->get("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/ad-script")
        ->assertOk();

    expect($antwort->headers->get('content-disposition'))->toContain('attachment');
    expect($antwort->getContent())->toContain('sophie.kraemer');

    // Jeder Abruf gibt einen neuen Schluessel aus - das vorige Skript kann
    // danach nicht mehr zurueckmelden.
    expect($liste->fresh()->script_token_hash)->not->toBeNull();
    expect($liste->fresh()->script_token_expires_at)->not->toBeNull();
});

test('jeder Abruf setzt einen neuen Schlüssel', function () {
    $kunde = checklistCustomer();
    $liste = checkliste($kunde);
    $liste->update(['username' => 'sophie.kraemer']);
    $token = checklistenToken(['checklist_manage']);
    $pfad = "/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/ad-script";

    test()->withToken($token)->get($pfad)->assertOk();
    $erster = $liste->fresh()->script_token_hash;

    test()->withToken($token)->get($pfad)->assertOk();

    expect($liste->fresh()->script_token_hash)->not->toBe($erster);
});

/** Ohne Benutzername hat das Skript nichts anzulegen. */
test('ohne Benutzername gibt es kein Skript', function () {
    $kunde = checklistCustomer();
    $liste = checkliste($kunde);
    $liste->update(['username' => null]);

    test()->withToken(checklistenToken(['checklist_manage']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/ad-script")
        ->assertStatus(422);
});

test('das Skript braucht das Verwaltungsrecht', function () {
    $kunde = checklistCustomer();
    $liste = checkliste($kunde);
    $liste->update(['username' => 'sophie.kraemer']);

    test()->withToken(checklistenToken(['checklist_request']))
        ->getJson("/api/mobile/customers/{$kunde->slug}/checklists/{$liste->id}/ad-script")
        ->assertForbidden();
});
