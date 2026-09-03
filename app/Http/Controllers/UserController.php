<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Einladung;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class UserController extends Controller
{
    // ADMIN Bereich
    public function index()
    {
        $users = User::paginate(Setting::seiteAdmin());
        $usersCount = User::all()->count();
        $userLastAdded = User::latest('created_at')->first();

        return view('admin.user.index', compact('users', 'usersCount', 'userLastAdded'));
    }

    public function create()
    {
        $roles = Role::all();
        $customers = Customer::all();

        return view('admin.user.create', compact('roles', 'customers'));
    }

    public function store(UserRequest $request)
    {
        $daten = $request->validated();
        $einladen = (bool) ($daten['einladen'] ?? false);
        unset($daten['einladen']);

        // Wer eingeladen wird, bekommt hier ein Kennwort, das niemand kennt -
        // auch der Administrator nicht. Es zu setzen ist nicht ueberfluessig:
        // die Spalte ist NOT NULL, und ein leeres Feld waere ein Konto, in das
        // sich jeder mit einem leeren Kennwort setzen koennte, wenn irgendwo
        // eine Pruefung schlampt.
        $daten['password'] = Hash::make($einladen ? Str::random(64) : $request->password);

        $user = User::create($daten);

        if (! $einladen) {
            return redirect(route('admin.user.index'));
        }

        return $this->einladungSenden($user, route('admin.user.index'));
    }

    /**
     * Einladung (erneut) verschicken. Der haeufige Fall ist nicht der Fehler,
     * sondern der Alltag: Die Mail ist im Spam gelandet, der Link ist
     * abgelaufen, der Kollege hat sie geloescht.
     */
    public function einladen(User $user)
    {
        return $this->einladungSenden($user, route('admin.user.edit', $user));
    }

    /**
     * Erzeugt einen Einladungs-Token und schickt ihn per Mail.
     *
     * Bewusst synchron: Ein Administrator soll sofort erfahren, ob die Mail
     * hinausging - und nicht erst der Benutzer, der drei Tage auf nichts
     * wartet. Deshalb faengt es den Fehler auch ab und zeigt ihn an, statt
     * eine 500er-Seite zu liefern.
     */
    private function einladungSenden(User $user, string $ziel)
    {
        if (! $user->email) {
            return redirect($ziel)->withErrors([
                'einladung' => __('Ohne E-Mail-Adresse lässt sich keine Einladung verschicken.'),
            ]);
        }

        try {
            $token = Password::broker('einladung')->createToken($user);

            $user->notify(new Einladung($token));

            // saveQuietly: Eine verschickte Einladung ist kein Aenderungs-
            // eintrag am Benutzer wert - im Protokoll stuende sonst neben
            // jedem Versand eine leere "Geaendert"-Zeile.
            $user->forceFill(['invited_at' => now()])->saveQuietly();
        } catch (Throwable $fehler) {
            report($fehler);

            return redirect($ziel)->withErrors([
                'einladung' => __('Die Einladung konnte nicht verschickt werden. Stimmen die Mail-Einstellungen?'),
            ]);
        }

        return redirect($ziel)->with('status', 'einladung-verschickt');
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $customers = Customer::all();

        return view('admin.user.edit', compact('user', 'roles', 'customers'));
    }

    public function update(User $user, UserRequest $request)
    {
        $userData = $request->validated();

        // Auf der Demo sind die vordefinierten Zugaenge vollstaendig gesperrt.
        // Nicht nur Passwort und Rolle: Der Schutz erkennt sie am Benutzernamen,
        // ein umbenannter Zugang waere also nicht mehr geschuetzt - und die
        // dokumentierte Anmeldung funktionierte nicht mehr.
        if ($user->istDemoGeschuetzt()) {
            return redirect(route('admin.user.edit', $user))
                ->withErrors(['demo' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')]);
        }

        if (! empty($userData['password'])) {
            $userData['password'] = Hash::make($request->password);
        } else {
            unset($userData['password']);
        }

        $user->update($userData);

        return redirect(route('admin.user.index', $user));
    }

    /**
     * Die zweite Stufe eines Nutzers abschalten.
     *
     * Der Fall dahinter ist banal und haeufig: verlorenes oder neues Telefon,
     * Wiederherstellungscodes im Papierkorb. Ohne diesen Weg bliebe nur der
     * Griff in die Datenbank. Es steht im Protokoll, wer es getan hat.
     */
    public function zweiteStufeZuruecksetzen(User $user)
    {
        if ($user->istDemoGeschuetzt()) {
            return redirect(route('admin.user.edit', $user))
                ->withErrors(['demo' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Mit Ereignisnamen: Ohne einen steht in der Protokollspalte "—", und
        // der Eintrag laesst sich auch nicht filtern.
        activity()
            ->event('zweite_stufe')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['objekt' => $user->name])
            ->log('Zweite Stufe zurückgesetzt');

        return redirect(route('admin.user.edit', $user))
            ->with('status', 'zweite-stufe-zurueckgesetzt');
    }

    public function destroy(User $user)
    {
        if ($user->istDemoGeschuetzt()) {
            return redirect(route('admin.user.edit', $user))
                ->withErrors(['demo' => __('Dieser Zugang gehört zur Demo und lässt sich nicht löschen. Selbst angelegte Benutzer schon.')]);
        }

        $user->delete();

        return redirect(route('admin.user.index'));
    }
}
