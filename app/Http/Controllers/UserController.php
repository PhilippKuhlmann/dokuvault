<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Invitation;
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
        $roles = $this->assignableRoles();
        $customers = Customer::all();

        return view('admin.user.create', compact('roles', 'customers'));
    }

    public function store(UserRequest $request)
    {
        $daten = $request->validated();
        $this->authorizeRole($daten['role_id']);
        $einladen = (bool) ($daten['einladen'] ?? false);
        unset($daten['einladen']);

        // Whoever is invited gets a password here that nobody knows - not even
        // the administrator. Setting it is not pointless: the column is NOT
        // NULL, and an empty field would be an account anyone could sit down
        // in with an empty password if a check somewhere were sloppy.
        //
        $daten['password'] = Hash::make($einladen ? Str::random(64) : $request->password);

        $user = User::create($daten);

        if (! $einladen) {
            return redirect(route('admin.user.index'));
        }

        return $this->einladungSenden($user, route('admin.user.index'));
    }

    /**
     * Send the invitation (again). The common case is not an error but
     * everyday life: the mail landed in spam, the link expired, the colleague
     * deleted it.
     */
    public function invite(User $user)
    {
        abort_unless(auth()->user()->mayManage($user), 403);

        return $this->einladungSenden($user, route('admin.user.edit', $user));
    }

    /**
     * Creates an invitation token and sends it by mail.
     *
     * Deliberately synchronous: an administrator should learn at once whether
     * the mail went out - and not the user first, waiting three days for
     * nothing. That is also why it catches the error and shows it instead of
     * delivering a 500 page.
     */
    private function einladungSenden(User $user, string $ziel)
    {
        if (! $user->email) {
            return redirect($ziel)->withErrors([
                'invitation' => __('Ohne E-Mail-Adresse lässt sich keine Einladung verschicken.'),
            ]);
        }

        try {
            $token = Password::broker('invitation')->createToken($user);

            $user->notify(new Invitation($token));

            // saveQuietly: a sent invitation is not a change
            // eintrag am Benutzer wert - im Protokoll stuende sonst neben
            // an empty "changed" row on every send.
            $user->forceFill(['invited_at' => now()])->saveQuietly();
        } catch (Throwable $fehler) {
            report($fehler);

            return redirect($ziel)->withErrors([
                'invitation' => __('Die Einladung konnte nicht verschickt werden. Stimmen die Mail-Einstellungen?'),
            ]);
        }

        return redirect($ziel)->with('status', 'einladung-verschickt');
    }

    public function edit(User $user)
    {
        abort_unless(auth()->user()->mayManage($user), 403);

        $roles = $this->assignableRoles();
        $customers = Customer::all();

        return view('admin.user.edit', compact('user', 'roles', 'customers'));
    }

    public function update(User $user, UserRequest $request)
    {
        abort_unless(auth()->user()->mayManage($user), 403);

        $userData = $request->validated();
        $this->authorizeRole($userData['role_id']);

        // On the demo the predefined accounts are locked completely. Not only
        // password and role: the protection recognises them by their username,
        // so a renamed account would no longer be protected - and the
        // documented sign-in would no longer work.
        if ($user->isDemoProtected()) {
            return redirect(route('admin.user.edit', $user))
                ->withErrors(['demo' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')]);
        }

        if (! empty($userData['password'])) {
            $userData['password'] = Hash::make($request->password);
        } else {
            unset($userData['password']);
        }

        // The box is called "deactivated", the table holds a timestamp.
        $sperren = (bool) ($userData['deactivated'] ?? false);
        unset($userData['deactivated']);

        // Not on oneself: whoever ticks the box on their own row falls out of
        // their own session on the next call - and cannot sign in to undo it.
        // The same reason the delete button is missing on one's own row.
        //
        if ($sperren && $user->id === auth()->id()) {
            // withInput(): otherwise every other change of the same form is
            // gone just because one box may not be ticked.
            return redirect(route('admin.user.edit', $user))
                ->withInput()
                ->withErrors(['deactivated' => __('Den eigenen Zugang können Sie nicht sperren.')]);
        }

        // Ein bereits gesperrter Zugang behaelt seinen Zeitpunkt: "seit wann"
        // should not start over on every save.
        $userData['deactivated_at'] = $sperren ? ($user->deactivated_at ?? now()) : null;

        $user->update($userData);

        return redirect(route('admin.user.index', $user));
    }

    /**
     * Switch off a user's second factor.
     *
     * The case behind it is mundane and common: a lost or new phone, recovery
     * codes in the bin. Without this way only reaching into the database
     * would be left. The log records who did it.
     */
    public function resetTwoFactor(User $user)
    {
        abort_unless(auth()->user()->mayManage($user), 403);

        if ($user->isDemoProtected()) {
            return redirect(route('admin.user.edit', $user))
                ->withErrors(['demo' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // With an event name: without one the log column shows "—", and the
        // entry cannot be filtered either.
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
        abort_unless(auth()->user()->mayManage($user), 403);

        if ($user->isDemoProtected()) {
            return redirect(route('admin.user.edit', $user))
                ->withErrors(['demo' => __('Dieser Zugang gehört zur Demo und lässt sich nicht löschen. Selbst angelegte Benutzer schon.')]);
        }

        $user->delete();

        return redirect(route('admin.user.index'));
    }

    /**
     * Only the roles the current admin may hand out - a delegate does not
     * get role 1 or anything above their own rights in the dropdown.
     */
    private function assignableRoles()
    {
        return Role::with('permissions')->get()
            ->filter(fn (Role $role) => auth()->user()->mayAssign($role))
            ->values();
    }

    private function authorizeRole(int|string $roleId): void
    {
        abort_unless(auth()->user()->mayAssign(Role::with('permissions')->findOrFail($roleId)), 403);
    }
}
