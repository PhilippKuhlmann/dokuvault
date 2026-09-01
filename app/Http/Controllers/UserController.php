<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // ADMIN Bereich
    public function index()
    {
        $users = User::paginate(20);
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
        $user = $request->validated();
        $user['password'] = Hash::make($request->password);

        User::create($user);

        return redirect(route('admin.user.index'));
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

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['name' => $user->name])
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
