<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Notifications\Testmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Der Mailversand der Installation.
 *
 * Was hier steht, gilt statt der .env - siehe
 * AppServiceProvider::mailEinstellungenAnwenden(). Wer nichts eintraegt,
 * behaelt die Werte aus der Umgebung.
 */
class MailSettingController extends Controller
{
    public function index()
    {
        return view('admin.mail.index', [
            'host' => Setting::wert(Setting::MAIL_HOST),
            'port' => Setting::wert(Setting::MAIL_PORT),
            'username' => Setting::wert(Setting::MAIL_USERNAME),
            // Das Kennwort selbst geht nie wieder hinaus - nur die Auskunft,
            // ob eines hinterlegt ist.
            'hatKennwort' => Setting::mailKennwort() !== null,
            'encryption' => Setting::wert(Setting::MAIL_ENCRYPTION, 'tls'),
            'from_address' => Setting::wert(Setting::MAIL_FROM_ADDRESS),
            'from_name' => Setting::wert(Setting::MAIL_FROM_NAME),
            // Woher die Werte kaemen, wenn hier nichts steht.
            'ausUmgebung' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $daten = $this->pruefen($request);

        Setting::setzen(Setting::MAIL_HOST, $daten['mail_host'] ?: null);
        Setting::setzen(Setting::MAIL_PORT, $daten['mail_port'] ?: null);
        Setting::setzen(Setting::MAIL_USERNAME, $daten['mail_username'] ?: null);
        // Leer heisst "ohne Verschluesselung" und ist eine bewusste Wahl -
        // nicht "nicht gesetzt".
        Setting::setzen(Setting::MAIL_ENCRYPTION, $daten['mail_encryption'] ?: '');
        Setting::setzen(Setting::MAIL_FROM_ADDRESS, $daten['mail_from_address'] ?: null);
        Setting::setzen(Setting::MAIL_FROM_NAME, $daten['mail_from_name'] ?: null);

        // Ein leeres Kennwortfeld heisst "unveraendert", nicht "loeschen" -
        // sonst waere jedes Speichern der uebrigen Felder ein stiller Verlust.
        // Zum Loeschen gibt es den eigenen Knopf.
        if (($daten['mail_password'] ?? '') !== '') {
            Setting::mailKennwortSetzen($daten['mail_password']);
        }

        return redirect(route('admin.mail.index'))->with('success', __('Einstellungen gespeichert.'));
    }

    /** Das hinterlegte Kennwort entfernen, ohne die uebrigen Felder zu leeren. */
    public function kennwortLoeschen()
    {
        Setting::mailKennwortSetzen(null);

        return redirect(route('admin.mail.index'))->with('success', __('Kennwort entfernt.'));
    }

    /**
     * Eine Testmail verschicken.
     *
     * Ohne sie erfaehrt ein Administrator erst dann, dass die Zugangsdaten
     * nicht stimmen, wenn ein Benutzer auf seine Einladung wartet.
     */
    public function test(Request $request)
    {
        $daten = $request->validate(
            ['test_an' => ['required', 'email']],
            [],
            ['test_an' => __('Empfänger')]
        );

        try {
            Notification::route('mail', $daten['test_an'])->notify(new Testmail);
        } catch (Throwable $fehler) {
            report($fehler);

            return redirect(route('admin.mail.index'))->withErrors([
                // Die Meldung des Servers mitgeben: "Verbindung fehlgeschlagen"
                // hilft niemandem, "535 Authentication failed" schon.
                'test_an' => __('Der Versand ist fehlgeschlagen: :grund', [
                    'grund' => str($fehler->getMessage())->limit(200),
                ]),
            ]);
        }

        return redirect(route('admin.mail.index'))
            ->with('success', __('Testmail verschickt an :adresse', ['adresse' => $daten['test_an']]));
    }

    /**
     * @return array<string, string|null>
     */
    private function pruefen(Request $request): array
    {
        return $request->validate([
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            // nullable, nicht required: Laravel behandelt einen leeren String
            // wie "nicht vorhanden" - mit required liesse sich "Ohne"
            // ueberhaupt nicht speichern.
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ], [], [
            'mail_host' => __('Server'),
            'mail_port' => __('Port'),
            'mail_username' => __('Benutzername'),
            'mail_password' => __('Kennwort'),
            'mail_encryption' => __('Verschlüsselung'),
            'mail_from_address' => __('Absenderadresse'),
            'mail_from_name' => __('Absendername'),
        ]);
    }
}
