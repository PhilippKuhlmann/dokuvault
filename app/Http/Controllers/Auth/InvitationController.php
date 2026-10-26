<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

/**
 * The invited user sets their own password.
 *
 * Same token mechanism as "forgot password", but through the broker
 * "einladung" with its own table and a long expiry - see config/auth.php.
 * Whoever gets here has proven access to the mailbox the invitation went to.
 */
class InvitationController extends Controller
{
    public function create(Request $request, string $token)
    {
        return view('auth.invitation', [
            'token' => $token,
            'username' => $request->query('username'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'username' => ['required'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Only an account with an open invitation. An accepted one sets its
        // password in the profile or via "forgot password", not here.
        $invitationOpen = User::where('username', $request->input('username'))->first()?->einladungOffen() ?? false;
        if (! $invitationOpen) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => __(Password::INVALID_TOKEN)]);
        }

        $status = Password::broker('einladung')->reset(
            $request->only('username', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                    // The invitation is redeemed - the user list shows it as
                    // done from now on, not as open. Its own column keeps the
                    // time: clearing invited_at alone would make a redeemed
                    // account look like one that was never invited.
                    'invited_at' => null,
                    'invitation_accepted_at' => now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => __($status)]);
        }

        // Deliberately not logged in right away: the first login takes the
        // way every later one takes - including the second factor, if the
        // administrator requires it.
        return redirect()->route('login')->with('status', __('Kennwort festgelegt. Sie können sich jetzt anmelden.'));
    }
}
