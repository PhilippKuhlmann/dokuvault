<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\InitialSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * Creates the first administrator of a fresh installation - see InitialSetup.
 */
class InitialSetupController extends Controller
{
    public function create()
    {
        abort_unless(InitialSetup::pending(), 404);

        return view('auth.setup', [
            'codeFromEnv' => filled(config('custom.setup_token')),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(InitialSetup::pending(), 404);

        $validated = $request->validate([
            'setup_code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (! InitialSetup::check($validated['setup_code'])) {
            return back()
                ->withInput($request->except('setup_code', 'password', 'password_confirmation'))
                ->withErrors(['setup_code' => __('Der Einrichtungscode stimmt nicht.')]);
        }

        DB::transaction(function () use ($validated) {
            // Checked again inside the transaction: two requests at the same
            // moment must not both create an administrator.
            abort_if(User::where('role_id', Role::IS_ADMIN)->lockForUpdate()->exists(), 404);

            // The production seeder creates role 1; an install without it
            // still gets one instead of a user pointing at nothing.
            Role::find(Role::IS_ADMIN) ?? Role::forceCreate(['id' => Role::IS_ADMIN, 'name' => 'Admin']);

            User::forceCreate([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id' => Role::IS_ADMIN,
            ]);

            InitialSetup::finish();
        });

        // Not logged in directly - the first login takes the same way as every
        // later one.
        return redirect()->route('login')
            ->with('status', __('DokuVault ist eingerichtet. Sie können sich jetzt anmelden.'));
    }
}
