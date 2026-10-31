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

        // Role 1 may do everything - regardless of what is ticked.
        //
        // That is the safeguard against locking oneself out: whoever
        // accidentally unticks "manage roles and permissions" in the role
        // management would otherwise never get back to the role management,
        // and the installation could only be rescued through the database.
        //
        // before() returns null for every other role - then the gates further
        // down decide.
        Gate::before(function (User $user, string $ability) {
            // isCustomer is a question of membership, not a permission. A
            // blanket "may do everything" turns it into its opposite: the
            // admin would count as a customer user, and the header would lose
            // the internal searches for them.
            if ($ability === 'isCustomer') {
                return null;
            }

            return $user->role_id === Role::IS_ADMIN ? true : null;
        });

        // One permission per admin menu item, plus the remote search. Before,
        // both areas hung on a fixed role id: either all of it or none.
        //
        //
        // Never the admin_* rights for a user tied to one customer: they apply
        // across customers. A role ticked by accident would otherwise give a
        // customer user access to other customers.
        foreach (array_merge(config('custom.admin_permissions'), config('custom.extra_permissions')) as $recht => $beschreibung) {
            // The remote search is meant for customer users too; only the
            // admin_* rights are cross-customer.
            $kundenNutzerAusschliessen = array_key_exists($recht, config('custom.admin_permissions'));

            Gate::define($recht, fn (User $user) => ! ($kundenNutzerAusschliessen && $user->customer_id !== null)
                && $user->hasPermission($recht));
        }

        // "May get in at all" - for the middleware and the link in the user
        // menu. Which area exactly is decided by the permission on the
        // respective route.
        Gate::define('admin_bereich', fn (User $user) => collect(array_keys(config('custom.admin_permissions')))
            ->contains(fn ($recht) => Gate::forUser($user)->allows($recht)));

        // Checklists have no policy of their own: whoever may work on them,
        // report a new employee or approve rights may see them - the same
        // rule as ChecklistController::VIEW_RIGHTS. Named like the others so
        // the global search finds them.
        Gate::define('checklist_viewAny', fn (User $user) => collect(['checklist_manage', 'checklist_request', 'checklist_approve'])
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
