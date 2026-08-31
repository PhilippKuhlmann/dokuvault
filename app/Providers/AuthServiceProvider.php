<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\GeneralPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\NAS' => 'App\Policies\NASPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('customer', [CustomerPolicy::class, 'customer']);

        Gate::define('see_hidden', [GeneralPolicy::class, 'see_hidden']);
        Gate::define('create_pdf', [GeneralPolicy::class, 'create_pdf']);

        // Die Rolle 1 darf alles - unabhaengig davon, was angehakt ist.
        //
        // Das ist die Absicherung gegen das Aussperren: Wer in der
        // Rollenverwaltung versehentlich "Rollen und Rechte verwalten"
        // abwaehlt, kaeme sonst nie wieder an die Rollenverwaltung, und die
        // Installation waere nur noch ueber die Datenbank zu retten.
        //
        // before() liefert null fuer alle anderen Rollen - dann entscheiden
        // die Gates weiter unten.
        Gate::before(function (User $user, string $ability) {
            // isCustomer ist eine Frage nach der Zugehoerigkeit, kein Recht.
            // Ein pauschales "darf alles" verfaelscht sie ins Gegenteil: Der
            // Admin galte damit als Kundennutzer, und die Kopfzeile verloere
            // fuer ihn die internen Suchen.
            if ($ability === 'isCustomer') {
                return null;
            }

            return $user->role_id === Role::IS_ADMIN ? true : null;
        });

        // Ein Recht je Admin-Menuepunkt, dazu die Fernwartungs-Suche. Vorher
        // hingen beide Bereiche an einer festen Rollen-Id: entweder ganz oder
        // gar nicht.
        foreach (array_merge(config('custom.admin_permissions'), config('custom.extra_permissions')) as $recht => $beschreibung) {
            Gate::define($recht, fn (User $user) => $user->hasPermission($recht));
        }

        // "Darf ueberhaupt hinein" - fuer die Middleware und den Link im
        // Benutzermenue. Welchen Bereich genau, entscheidet das Recht an der
        // jeweiligen Route.
        Gate::define('admin_bereich', fn (User $user) => collect(array_keys(config('custom.admin_permissions')))
            ->contains(fn ($recht) => Gate::forUser($user)->allows($recht)));

        $resources = config('custom.permissions');

        foreach ($resources as $resource) {
            $policyClass = "App\Policies\\{$resource}Policy";
            $gateName = strtolower($resource);

            Gate::define("{$gateName}_viewAny", [$policyClass, 'viewAny']);
            Gate::define("{$gateName}_create", [$policyClass, 'create']);
            Gate::define("{$gateName}_update", [$policyClass, 'update']);
            Gate::define("{$gateName}_delete", [$policyClass, 'delete']);
        }

    }
}
