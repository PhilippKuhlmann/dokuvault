<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        Gate::define('isAdmin', function (User $user) {
            return $user->role->id == Role::IS_ADMIN;
        });

        Gate::define('isCustomer', function (User $user) {
            return $user->role->id == 98 || $user->role->id == 99;
        });

        Gate::define('isCustomerR', function (User $user) {
            return $user->role->id == 98;
        });

        Gate::define('isCustomerRW', function (User $user) {
            return $user->role->id == 99;
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
}
