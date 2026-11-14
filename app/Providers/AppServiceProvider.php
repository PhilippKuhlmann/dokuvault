<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\AlpineJson;
use App\Support\BackupHealth;
use App\Support\BackupSettings;
use App\Support\Changelog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
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
        // @alpineJson($wert): an Alpine argument the CSP build can read, see
        // AlpineJson. For strings and numbers @js stays fine.
        Blade::directive('alpineJson', fn (string $ausdruck) => '<?php echo '.AlpineJson::class."::attribut({$ausdruck}); ?>");

        // A session ended by a password change elsewhere goes to the login
        // again. Since Laravel 13 AuthenticateSession has no target of its
        // own and answers a bare 401 - a blank page in the browser.
        AuthenticateSession::redirectUsing(fn ($request) => $request->expectsJson() ? null : route('login'));

        // The DokuVault backup is configured in the admin area; its settings
        // replace config/backup.php at runtime. Never at the price of booting.
        try {
            BackupSettings::anwenden();
        } catch (Throwable) {
        }

        // Outcome of each backup run, for the state on the dashboard.
        Event::listen(BackupWasSuccessful::class, [BackupHealth::class, 'erfolg']);
        Event::listen(BackupHasFailed::class, [BackupHealth::class, 'fehler']);

        // Every log entry carries its customer: the customer itself, or the
        // customer of the changed object. The customer dashboard and the
        // customer filter in the log query this column instead of one
        // OR EXISTS per object type - see the activity_log migration of
        // 2026-10-08. The subject is already in memory (performedOn).
        Activity::creating(function (Activity $eintrag) {
            if ($eintrag->customer_id !== null) {
                return;
            }
            $objekt = $eintrag->subject;
            $eintrag->customer_id = $objekt instanceof Customer
                ? $objekt->getKey()
                : ($objekt?->getAttribute('customer_id') ?? null);
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->registerSearch();
        $this->applyMailSettings();

        // What a password has to look like - set in one place, effective in
        // all four: own profile, creation by an administrator, forgotten
        // password, redeeming an invitation. Until then nothing stood here and
        // Laravel's default of eight characters applied silently.
        //
        // As a function, not as a value: that way the setting is read when
        // checking and not at boot - at boot the table does not even exist on
        // the first "artisan migrate".
        Password::defaults(fn () => Setting::passwordRule());

        // Livewire checks temporary uploads with a rule of its own and lets
        // through at most 12 MB unless told otherwise. The application allows
        // more - through an ordinary form that worked, through a modal it did
        // not. Two limits, and nobody knew the smaller one. Now the same
        // number applies everywhere, maintained in the admin area.
        //
        // try: the number comes from the database, and this line also runs on
        // the first "artisan migrate", when the table does not exist yet.
        try {
            $grenze = Setting::uploadMaxKb();
        } catch (Throwable) {
            $grenze = (int) config('custom.datei_max_kb');
        }

        config(['livewire.temporary_file_upload.rules' => [
            'required', 'file', 'max:'.$grenze,
        ]]);

        $this->applySessionSettings();

        // role_id, not role->id: a user without a role would throw here.
        Gate::define('isAdmin', function (User $user) {
            return (int) $user->role_id === Role::IS_ADMIN;
        });

        /*
         * Is this user tied to one customer?
         *
         * By customer_id, not by role. The gate used to ask for the role ids
         * 98 and 99 - from a time when roles were fixed. Roles have long been
         * created in the admin area and get consecutive numbers there; the
         * customer roles of this installation have 11 and 12. The gate was
         * therefore never true, and the header offered every customer user the
         * customer search and remote access - both of which lead nowhere for
         * them.
         *
         * The isCustomer middleware and the API have long asked the same
         * question this way. The earlier gradations isCustomerR and
         * isCustomerRW are gone: whether someone may only read is told by the
         */
        Gate::define('isCustomer', function (User $user) {
            return $user->customer_id !== null;
        });

        // The version number stands in the masthead of the sign-in page and in
        // the sidebar. The composer stays on '*' because both views need it -
        // thanks to Changelog::version() reading the file costs one access per
        // request, not one per rendered view.
        View::composer('*', function ($view) {
            $view->with('version', Changelog::version());
        });
    }

    /**
     * Free-text search across one or more columns.
     *
     * The reason for a method of its own are the LIKE placeholders. A search
     * term like "SRV_01" also found "SRV101" without escaping, and a search
     * for "%" returned the entire stock - measured: 863 of 863 activity
     * entries.
     *
     * The ESCAPE clause is not optional. MySQL takes the backslash as an
     * escape character by itself, SQLite does not: there the escaped pattern
     * found nothing at all without ESCAPE. Since the tests run on SQLite and
     * production on MySQL, both have to be right.
     */
    protected function registerSearch(): void
    {
        // "%term%" - finds the term in any position.
        Builder::macro('whereEnthaelt', fn (string|array $spalten, string $begriff) => $this->sucheAnwenden($spalten, '%'.static::sucheMaskieren($begriff).'%'));

        // "term%" - only at the start. An expensive difference: on an indexed
        // column the prefix form can use the index, "%term%" cannot.
        // Measured at 4 million AD users: 3 ms against 2788 ms.
        Builder::macro('whereBeginntMit', fn (string|array $spalten, string $begriff) => $this->sucheAnwenden($spalten, static::sucheMaskieren($begriff).'%'));

        Builder::macro('sucheAnwenden', function (string|array $spalten, string $muster) {
            return $this->where(function ($abfrage) use ($spalten, $muster) {
                foreach ((array) $spalten as $spalte) {
                    // ESCAPE bound instead of as a literal: MySQL and SQLite
                    // treat backslashes in strings differently.
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
     * Put the SMTP data maintained in the admin area over the .env.
     *
     * Only what is set: whoever enters nothing keeps the values from the
     * .env - just as before this setting existed. That keeps an installation
     * running that configures its sending through the environment.
     *
     *
     * The access is wrapped because it runs early: on a fresh installation
     * the table does not exist yet, and an "artisan migrate" must not fail
     * over it.
     */

    /**
     * Session lifetime and "stay signed in" from the settings.
     *
     * The values come from the database, read before the session starts -
     * StartSession runs as middleware, so after the providers. If the
     * settings hold nothing, SESSION_LIFETIME from the .env applies.
     *
     * try: these lines also run on the first "composer install" in a fresh
     * checkout - package:discover starts before there is an .env or a table.
     * A CI run has failed over exactly that before.
     * gescheitert.
     */
    private function applySessionSettings(): void
    {
        // Do not even start without a key: the guard pulls the session, the
        // session is encrypted, and without APP_KEY the encrypter throws.
        // Verschluessler.
        if (! config('app.key')) {
            return;
        }

        try {
            $tage = Setting::rememberTage();

            config([
                'session.lifetime' => Setting::sitzungMinuten(),
                'session.expire_on_close' => Setting::sitzungBeimSchliessen(),
            ]);
        } catch (Throwable) {
            // Without a table the default. For the session it already stands
            // in the configuration anyway, only the number is missing here.
            $tage = (int) config('custom.remember_days', 30);
        }

        // Outside the try, and that is the point: without this call Laravel's
        // default of 400 days applies. Inside it, an error reading the setting
        // would not mean "it stays at 30" but "it is 400 again" - a stolen
        // laptop would be permanent access. An existing test uncovered exactly
        // that.
        Auth::guard('web')->setRememberDuration(60 * 24 * $tage);
    }

    private function applyMailSettings(): void
    {
        try {
            $host = trim((string) Setting::wert(Setting::MAIL_HOST));
        } catch (Throwable) {
            return;
        }

        if ($host === '') {
            return;
        }

        $kennwort = Setting::mailPassword();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) Setting::wert(Setting::MAIL_PORT, 587),
            'mail.mailers.smtp.username' => Setting::wert(Setting::MAIL_USERNAME) ?: null,
            'mail.mailers.smtp.password' => $kennwort,
            // Empty really means "without" - null would be the same here, but
            // the select knows three states and should be able to hit all three.
            'mail.mailers.smtp.encryption' => Setting::wert(Setting::MAIL_ENCRYPTION) ?: null,
        ]);

        if ($absender = trim((string) Setting::wert(Setting::MAIL_FROM_ADDRESS))) {
            config(['mail.from.address' => $absender]);
        }

        if ($name = trim((string) Setting::wert(Setting::MAIL_FROM_NAME))) {
            config(['mail.from.name' => $name]);
        }

        // config/backup.php reads its sender from the .env when the config
        // loads - the backup mail went out as hello@example.com and the mail
        // server refused it. It gets the same sender as every other mail.
        config(['backup.notifications.mail.from' => config('mail.from')]);
    }
}
