<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\LicenseSoftware;
use App\Models\Mailbox;
use App\Models\MailboxProvider;
use App\Models\Site;

/**
 * Die Nutzlast entspricht dem, was microsoft365-doku.ps1 aus den Antworten von
 * /v1.0/users, /v1.0/domains und /v1.0/subscribedSkus baut. Sie ist
 * nachgestellt, nicht von einem echten Tenant aufgezeichnet - das Skript
 * selbst ist gegen Graph noch nicht gelaufen.
 */
function microsoft365Payload(): array
{
    return [
        'tenant' => '00000000-1111-2222-3333-444444444444',
        'mailboxes' => [
            [
                'identifier' => 'user-guid-1',
                'name' => 'Erika Mustermann',
                'mail' => 'erika@kunde.de',
                'username' => 'erika@kunde.onmicrosoft.com',
            ],
            [
                'identifier' => 'user-guid-2',
                'name' => 'Info',
                'mail' => 'info@kunde.de',
                'username' => 'info@kunde.onmicrosoft.com',
            ],
        ],
        'domains' => [
            ['identifier' => 'kunde.de', 'name' => 'kunde.de'],
        ],
        'licences' => [
            ['identifier' => 'sku-guid-1', 'name' => 'O365 BUSINESS PREMIUM', 'gebucht' => 15, 'belegt' => 12],
        ],
    ];
}

test('Microsoft-365-Agent legt Postfaecher, Domains und Lizenzen beim Kunden des Tokens an', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site, 'M365');

    $this->withToken($plain)->postJson('/api/agent/microsoft365', microsoft365Payload())
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'mailboxes_documented' => 2,
            'domains_documented' => 1,
            'licences_documented' => 1,
        ]);

    $postfach = Mailbox::where('customer_id', $customer->id)->where('agent_identifier', 'user-guid-1')->first();
    expect($postfach->name)->toBe('Erika Mustermann');
    expect($postfach->mailAdress)->toBe('erika@kunde.de');
    expect($postfach->mailboxProvider->name)->toBe('Microsoft 365');

    // Der Anbieter wird einmal angelegt, nicht je Postfach.
    expect(MailboxProvider::where('name', 'Microsoft 365')->count())->toBe(1);

    expect(Domain::where('customer_id', $customer->id)->first()->name)->toBe('kunde.de');

    // Die Stueckzahl steht im Namen: die Tabelle hat keine Spalte dafuer, und
    // "wie viele sind belegt?" ist beim Kunden die erste Frage.
    expect(LicenseSoftware::where('customer_id', $customer->id)->first()->name)
        ->toBe('O365 BUSINESS PREMIUM (12 von 15 belegt)');
});

test('erneuter Lauf aktualisiert, statt zu verdoppeln', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/microsoft365', microsoft365Payload())->assertOk();

    $geaendert = microsoft365Payload();
    $geaendert['licences'][0]['belegt'] = 14;
    $this->withToken($plain)->postJson('/api/agent/microsoft365', $geaendert)->assertOk();

    expect(Mailbox::where('customer_id', $customer->id)->count())->toBe(2);
    expect(Domain::where('customer_id', $customer->id)->count())->toBe(1);
    expect(LicenseSoftware::where('customer_id', $customer->id)->count())->toBe(1);
    expect(LicenseSoftware::first()->name)->toBe('O365 BUSINESS PREMIUM (14 von 15 belegt)');
});

test('ein von Hand gesetztes Kennwort und ein anderer Anbieter ueberleben den naechsten Lauf', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/microsoft365', microsoft365Payload())->assertOk();

    $eigener = MailboxProvider::create([
        'name' => 'Eigener Exchange',
        'pop3server' => 'mail.kunde.de', 'pop3port' => '995',
        'imapserver' => 'mail.kunde.de', 'imapport' => '993',
        'smtpserver' => 'mail.kunde.de', 'smtpport' => '587',
    ]);
    $postfach = Mailbox::where('agent_identifier', 'user-guid-1')->first();
    $postfach->update(['password' => 'geheim123', 'mailbox_provider_id' => $eigener->id]);

    $this->withToken($plain)->postJson('/api/agent/microsoft365', microsoft365Payload())->assertOk();

    expect($postfach->fresh()->password)->toBe('geheim123');
    expect($postfach->fresh()->mailbox_provider_id)->toBe($eigener->id);
});

test('ein Token trifft keine Daten eines anderen Kunden', function () {
    $kundeA = Customer::factory()->create();
    $kundeB = Customer::factory()->create();
    $standortA = Site::factory()->create(['customer_id' => $kundeA->id]);

    $fremde = Domain::create([
        'customer_id' => $kundeB->id,
        'name' => 'fremde-firma.de',
        'agent_identifier' => 'kunde.de',
    ]);

    [$token, $plain] = AgentToken::generateFor($kundeA, $standortA);
    $this->withToken($plain)->postJson('/api/agent/microsoft365', microsoft365Payload())->assertOk();

    expect($fremde->fresh()->name)->toBe('fremde-firma.de')
        ->and($fremde->fresh()->customer_id)->toBe($kundeB->id);
    expect(Mailbox::where('customer_id', $kundeB->id)->count())->toBe(0);
    expect(Domain::where('customer_id', $kundeA->id)->count())->toBe(1);
});

test('ohne Pflichtfelder: 422', function () {
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/microsoft365', ['domains' => [['name' => 'ohne Kennung']]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('domains.0.identifier');
});

test('ohne gültigen Agent-Token: 401', function () {
    $this->postJson('/api/agent/microsoft365', [])->assertUnauthorized();
    $this->withToken('doc_falsch')->postJson('/api/agent/microsoft365', microsoft365Payload())->assertUnauthorized();
});
