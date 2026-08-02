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

        // Auf der Demo bleiben die vordefinierten Zugaenge nutzbar: Passwort und
        // Rolle sind gesperrt. Beides wuerde alle uebrigen Besucher aussperren -
        // ein geaendertes Passwort direkt, eine herabgestufte Rolle genauso.
        if ($user->istDemoGeschuetzt()) {
            unset($userData['password'], $userData['role_id'], $userData['customer_id']);
            $user->update($userData);

            return redirect(route('admin.user.edit', $user))
                ->withErrors(['demo' => 'Passwort und Rolle dieses Demo-Zugangs sind gesperrt. Alles Übrige wurde gespeichert.']);
        }

        if (! empty($userData['password'])) {
            $userData['password'] = Hash::make($request->password);
        } else {
            unset($userData['password']);
        }

        $user->update($userData);

        return redirect(route('admin.user.index', $user));
    }

    public function destroy(User $user)
    {
        if ($user->istDemoGeschuetzt()) {
            return redirect(route('admin.user.edit', $user))
                ->withErrors(['demo' => 'Dieser Zugang gehört zur Demo und lässt sich nicht löschen. Selbst angelegte Benutzer schon.']);
        }

        $user->delete();

        return redirect(route('admin.user.index'));
    }
}
