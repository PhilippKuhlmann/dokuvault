<?php

namespace App\Http\Controllers\API\Agent;

use App\Models\Domain;
use App\Models\LicenseSoftware;
use App\Models\Mailbox;
use App\Models\MailboxProvider;
use Illuminate\Http\Request;

/** A Microsoft 365 tenant reports its domains, mailboxes and licences. Customer-wide. */
class Microsoft365Controller extends AgentEndpoint
{
    /**
     * Nimmt die aus Microsoft Graph gemeldeten Postfächer, Domains und
     * Lizenzen entgegen (Upsert über agent_identifier = Objekt-Id im Tenant).
     *
     * Ohne Standort: Postfächer, Domains und Lizenzen hängen am Kunden, nicht
     * an einem Ort - ein Postfach steht in keinem Serverraum.
     */
    public function microsoft365(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');

        $data = $request->validate([
            'tenant' => ['nullable', 'string', 'max:255'],
            'mailboxes' => ['nullable', 'array'],
            'mailboxes.*.identifier' => ['required_with:mailboxes', 'string', 'max:255'],
            'mailboxes.*.name' => ['nullable', 'string', 'max:255'],
            'mailboxes.*.mail' => ['nullable', 'string', 'max:255'],
            'mailboxes.*.username' => ['nullable', 'string', 'max:255'],
            'domains' => ['nullable', 'array'],
            'domains.*.identifier' => ['required_with:domains', 'string', 'max:255'],
            'domains.*.name' => ['required_with:domains', 'string', 'max:255'],
            'licences' => ['nullable', 'array'],
            'licences.*.identifier' => ['required_with:licences', 'string', 'max:255'],
            'licences.*.name' => ['required_with:licences', 'string', 'max:255'],
            'licences.*.gebucht' => ['nullable', 'integer'],
            'licences.*.belegt' => ['nullable', 'integer'],
        ]);

        $postfaecher = 0;
        if ($data['mailboxes'] ?? false) {
            // Wie proxmox() es mit dem Betriebssystem tut: den Anbieter einmal
            // anlegen und danach wiederverwenden. Die Serverangaben sind bei
            // Microsoft 365 fuer alle Tenants dieselben und in der Tabelle
            // Pflicht; gesetzt werden sie nur beim Anlegen, ein von Hand
            // geaenderter Eintrag bleibt also stehen.
            $anbieter = MailboxProvider::firstOrCreate(['name' => 'Microsoft 365'], [
                'pop3server' => 'outlook.office365.com',
                'pop3port' => '995',
                'imapserver' => 'outlook.office365.com',
                'imapport' => '993',
                'smtpserver' => 'smtp.office365.com',
                'smtpport' => '587',
            ]);

            foreach ($data['mailboxes'] as $m) {
                $postfach = Mailbox::firstOrNew([
                    'customer_id' => $customer->id,
                    'agent_identifier' => $m['identifier'],
                ]);

                // Nur beim Anlegen setzen: wer das Postfach spaeter einem
                // anderen Anbieter zugeordnet hat, wird nicht ueberstimmt.
                $postfach->mailbox_provider_id ??= $anbieter->id;

                $postfach->fill([
                    'name' => $m['name'] ?? null,
                    'mailAdress' => $m['mail'] ?? null,
                    'username' => $m['username'] ?? null,
                    // 'password' bleibt unangetastet (manuell gepflegt)
                ])->save();

                $postfaecher++;
            }
        }

        $domains = 0;
        foreach ($data['domains'] ?? [] as $d) {
            Domain::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $d['identifier']],
                ['name' => $d['name']]
            );
            $domains++;
        }

        $lizenzen = 0;
        foreach ($data['licences'] ?? [] as $l) {
            LicenseSoftware::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $l['identifier']],
                [
                    'name' => $this->licenseName($l),
                    'abo' => true,
                    // 'key' bleibt leer: einen Schluessel gibt es bei einem
                    // Microsoft-365-Abonnement nicht.
                ]
            );
            $lizenzen++;
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'tenant' => $data['tenant'] ?? null,
            'mailboxes_documented' => $postfaecher,
            'domains_documented' => $domains,
            'licences_documented' => $lizenzen,
        ]);
    }

    /**
     * Baut den Namen der Lizenz. Die Stückzahl gehört mit hinein: die Tabelle
     * hat keine Spalte dafür, und "wie viele der gebuchten Lizenzen sind
     * eigentlich belegt?" ist beim Kunden die erste Frage. Der Eintrag gehört
     * dem Agenten (agent_identifier), der Name darf sich also mit jedem Lauf
     * an die tatsächliche Zahl anpassen.
     */
    protected function licenseName(array $lizenz): string
    {
        $name = trim($lizenz['name']);

        if (! isset($lizenz['gebucht'])) {
            return $name;
        }

        return $name.' ('.($lizenz['belegt'] ?? 0).' von '.$lizenz['gebucht'].' belegt)';
    }
}
