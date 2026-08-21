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
     * whereEnthaelt(): Freitextsuche ueber eine oder mehrere Spalten.
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
        Builder::macro('whereEnthaelt', function (string|array $spalten, string $begriff) {
            $muster = '%'.addcslashes($begriff, '%_\\').'%';

            return $this->where(function ($abfrage) use ($spalten, $muster) {
                foreach ((array) $spalten as $spalte) {
                    $abfrage->orWhereRaw(
                        $abfrage->getGrammar()->wrap($spalte).' LIKE ? ESCAPE ?',
                        [$muster, '\\']
                    );
                }
            });
        });
    }
}
