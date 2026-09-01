<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->sucheRegistrieren();
        $this->mailEinstellungenAnwenden();

        // Ohne Angabe gilt das "Angemeldet bleiben"-Cookie fuenf Jahre - siehe
        // config/custom.php. Ein gestohlenes Notebook waere damit ein
        // Dauerzugang.
        //
        // Nur mit Schluessel: Der Guard zieht die Sitzung, die Sitzung ist
        // verschluesselt (config/session.php), und ohne APP_KEY wirft der
        // Verschluessler. Genau das passiert bei "composer install" auf einem
        // frischen Auscheck-Verzeichnis - dort laeuft package:discover, bevor
        // es eine .env gibt, und der ganze Lauf brach daran ab.
        if (config('app.key')) {
            Auth::guard('web')->setRememberDuration(
                60 * 24 * (int) config('custom.remember_days', 30)
            );
        }

        Gate::define('isAdmin', function (User $user) {
            return $user->role->id == Role::IS_ADMIN;
        });

        /*
         * Ist dieser Nutzer auf einen Kunden festgelegt?
         *
         * An der customer_id, nicht an der Rolle. Vorher fragte das Gate nach
         * den Rollen-Ids 98 und 99 - aus einer Zeit, in der es feste Rollen
         * gab. Rollen entstehen laengst im Adminbereich und bekommen dort
         * fortlaufende Nummern; die Kundenrollen dieser Installation haben 11
         * und 12. Das Gate war damit nie wahr, und die Kopfzeile bot jedem
         * Kundennutzer die Kundensuche und die Fernwartung an - beides fuehrt
         * fuer ihn ins Leere.
         *
         * Dieselbe Frage stellen die isCustomer-Middleware und die API
         * laengst so. Die frueheren Abstufungen isCustomerR und isCustomerRW
         * sind entfallen: Ob jemand nur lesen darf, sagt die Rechtematrix.
         */
        Gate::define('isCustomer', function (User $user) {
            return $user->customer_id !== null;
        });

        View::composer('*', function ($view) {

            $changelog = file_get_contents(base_path('changelog.md'));

            preg_match('/## (\d{2}\.\d{2}\.\d{2})/', $changelog, $matches);
            $version = $matches[1] ?? 'Unbekannt';

            $view->with('version', $version);
        });
    }

    /**
     * Freitextsuche ueber eine oder mehrere Spalten.
     *
     * Der Grund fuer die eigene Methode sind die Platzhalter von LIKE. Ein
     * Suchbegriff wie "SRV_01" fand ohne Maskierung auch "SRV101", und die
     * Suche nach "%" lieferte den gesamten Bestand - gemessen: 863 von 863
     * Protokolleintraegen.
     *
     * Die ESCAPE-Klausel ist nicht optional. MySQL nimmt den Backslash von
     * selbst als Escape-Zeichen, SQLite nicht: Dort fand das maskierte Muster
     * ohne ESCAPE gar nichts mehr. Da die Tests auf SQLite laufen und die
     * Produktion auf MySQL, muss beides stimmen.
     */
    protected function sucheRegistrieren(): void
    {
        // "%begriff%" - findet den Begriff an jeder Stelle.
        Builder::macro('whereEnthaelt', fn (string|array $spalten, string $begriff) => $this->sucheAnwenden($spalten, '%'.static::sucheMaskieren($begriff).'%'));

        // "begriff%" - nur am Anfang. Teurer Unterschied: Auf einer indizierten
        // Spalte kann die Praefix-Form den Index nutzen, "%begriff%" nicht.
        // Gemessen bei 4 Millionen AD-Benutzern: 3 ms gegen 2788 ms.
        Builder::macro('whereBeginntMit', fn (string|array $spalten, string $begriff) => $this->sucheAnwenden($spalten, static::sucheMaskieren($begriff).'%'));

        Builder::macro('sucheAnwenden', function (string|array $spalten, string $muster) {
            return $this->where(function ($abfrage) use ($spalten, $muster) {
                foreach ((array) $spalten as $spalte) {
                    // ESCAPE gebunden statt als Literal: MySQL und SQLite
                    // behandeln Backslashes in Zeichenketten unterschiedlich.
                    $abfrage->orWhereRaw(
                        $abfrage->getGrammar()->wrap($spalte).' LIKE ? ESCAPE ?',
                        [$muster, '\\']
                    );
                }
            });
        });

        Builder::macro('sucheMaskieren', fn (string $begriff) => addcslashes($begriff, '%_\\'));
    }

    /**
     * Die im Adminbereich gepflegten SMTP-Daten ueber die .env legen.
     *
     * Nur was gesetzt ist: Wer nichts eintraegt, behaelt die Werte aus der
     * .env - so wie vor dieser Einstellung. Damit bleibt auch eine
     * Installation lauffaehig, die ihren Versand ueber die Umgebung
     * konfiguriert.
     *
     * Der Zugriff ist gekapselt, weil er frueh laeuft: Bei einer frischen
     * Installation gibt es die Tabelle noch nicht, und ein "artisan migrate"
     * darf daran nicht scheitern.
     */
    private function mailEinstellungenAnwenden(): void
    {
        try {
            $host = trim((string) Setting::wert(Setting::MAIL_HOST));
        } catch (Throwable) {
            return;
        }

        if ($host === '') {
            return;
        }

        $kennwort = Setting::mailKennwort();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) Setting::wert(Setting::MAIL_PORT, 587),
            'mail.mailers.smtp.username' => Setting::wert(Setting::MAIL_USERNAME) ?: null,
            'mail.mailers.smtp.password' => $kennwort,
            // Leer heisst wirklich "ohne" - null waere hier dasselbe, aber die
            // Auswahl kennt drei Zustaende und soll alle drei treffen koennen.
            'mail.mailers.smtp.encryption' => Setting::wert(Setting::MAIL_ENCRYPTION) ?: null,
        ]);

        if ($absender = trim((string) Setting::wert(Setting::MAIL_FROM_ADDRESS))) {
            config(['mail.from.address' => $absender]);
        }

        if ($name = trim((string) Setting::wert(Setting::MAIL_FROM_NAME))) {
            config(['mail.from.name' => $name]);
        }
    }
}
