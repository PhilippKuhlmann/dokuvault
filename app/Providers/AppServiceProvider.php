<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
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
}
