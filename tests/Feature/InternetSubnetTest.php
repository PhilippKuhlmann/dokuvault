<?php

use App\Models\Customer;
use App\Models\InternetConnection;
use App\Models\Site;

function anschlussUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

function anschlussDaten(Site $site, array $extra = []): array
{
    return array_merge(['site_id' => $site->id, 'provider' => 'Telekom'], $extra);
}

test('ein Anschluss ohne Netz lässt sich weiterhin anlegen', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = anschlussUmgebung();

    imModal('internetconnection', $customer, anschlussDaten($site))
        ->assertHasNoErrors();

    expect(InternetConnection::first()->subnet)->toBeNull();
});

test('Netz und Gateway werden gespeichert', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = anschlussUmgebung();

    imModal('internetconnection', $customer, anschlussDaten($site, [
        'subnet' => '203.0.113.16/28', 'subnet_gateway' => '203.0.113.17',
    ]))->assertHasNoErrors();

    $ic = InternetConnection::first();
    expect($ic->subnet)->toBe('203.0.113.16/28');
    expect($ic->subnet_gateway)->toBe('203.0.113.17');
});

test('ein Netz ohne Präfix wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = anschlussUmgebung();

    imModal('internetconnection', $customer, anschlussDaten($site, ['subnet' => '203.0.113.16']))
        ->assertHasErrors('form.subnet');

    expect(InternetConnection::count())->toBe(0);
});

test('eine Hostadresse statt der Netzadresse wird abgelehnt und die richtige genannt', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = anschlussUmgebung();

    $antwort = imModal('internetconnection', $customer, anschlussDaten($site, [
        'subnet' => '203.0.113.17/28',
    ]));

    // Die Meldung nennt das richtige Netz - ohne sie muesste man selbst
    // rechnen. Im Modal stehen die Fehler an der Komponente, nicht in der
    // Sitzung.
    $antwort->assertHasErrors('form.subnet');
    expect($antwort->errors()->first('form.subnet'))->toContain('203.0.113.16/28');
});

test('eine zu große Präfixlänge wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = anschlussUmgebung();

    imModal('internetconnection', $customer, anschlussDaten($site, ['subnet' => '203.0.113.16/33']))
        ->assertHasErrors('form.subnet');
});

test('ein Gateway außerhalb des Netzes wird abgelehnt', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = anschlussUmgebung();

    imModal('internetconnection', $customer, anschlussDaten($site, [
        'subnet' => '203.0.113.16/28', 'subnet_gateway' => '203.0.113.99',
    ]))->assertHasErrors('form.subnet_gateway');

    expect(InternetConnection::count())->toBe(0);
});

test('ein Netz ohne Gateway ist erlaubt', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = anschlussUmgebung();

    imModal('internetconnection', $customer, anschlussDaten($site, ['subnet' => '203.0.113.16/28']))
        ->assertHasNoErrors();

    expect(InternetConnection::first()->subnet_gateway)->toBeNull();
});

test('ein IPv6-Netz wird angenommen', function () {
    $this->actingAs(userWithPermissions(['internetconnection_create']));
    [$customer, $site] = anschlussUmgebung();

    imModal('internetconnection', $customer, anschlussDaten($site, [
        'subnet' => '2001:db8:abcd::/48', 'subnet_gateway' => '2001:db8:abcd::1',
    ]))->assertHasNoErrors();

    expect(InternetConnection::first()->subnet)->toBe('2001:db8:abcd::/48');
});

test('der nutzbare Bereich rechnet Netz- und Broadcast-Adresse heraus', function () {
    expect((new InternetConnection(['subnet' => '203.0.113.16/28']))->nutzbarerBereich())
        ->toBe('203.0.113.17 – 203.0.113.30 (14 Adressen)');

    expect((new InternetConnection(['subnet' => '192.0.2.0/24']))->nutzbarerBereich())
        ->toBe('192.0.2.1 – 192.0.2.254 (254 Adressen)');
});

test('ohne Netz, bei IPv6 und bei winzigen Präfixen gibt es keinen Bereich', function () {
    // /31 und /32 haben keinen nutzbaren Bereich in diesem Sinn, IPv6 keine
    // Broadcast-Adresse - dort waere jede Zahl irrefuehrend.
    expect((new InternetConnection)->nutzbarerBereich())->toBeNull();
    expect((new InternetConnection(['subnet' => '203.0.113.16/31']))->nutzbarerBereich())->toBeNull();
    expect((new InternetConnection(['subnet' => '2001:db8::/64']))->nutzbarerBereich())->toBeNull();
});

test('die Liste zeigt das Netz nur, wenn eines hinterlegt ist', function () {
    $nutzer = userWithPermissions(['internetconnection_viewAny']);
    [$customer, $site] = anschlussUmgebung();

    InternetConnection::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'provider' => 'Ohne Netz',
    ]);

    $this->actingAs($nutzer)->get("/{$customer->slug}/internetconnection")
        ->assertDontSee('Geroutetes Netz');

    InternetConnection::create([
        'customer_id' => $customer->id, 'site_id' => $site->id, 'provider' => 'Mit Netz',
        'subnet' => '203.0.113.16/28', 'subnet_gateway' => '203.0.113.17',
    ]);

    $this->actingAs($nutzer)->get("/{$customer->slug}/internetconnection")
        ->assertSee('Geroutetes Netz')
        ->assertSee('203.0.113.16/28')
        ->assertSee('203.0.113.17 – 203.0.113.30 (14 Adressen)');
});
