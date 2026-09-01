<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

/**
 * Der eingeladene Benutzer gibt sich sein Kennwort.
 *
 * Laeuft ueber denselben Token-Mechanismus wie "Kennwort vergessen", aber
 * ueber den Broker "einladung" mit langer Frist - siehe config/auth.php. Wer
 * hier ankommt, hat bewiesen, dass er das Postfach hat, in das die Einladung
 * ging.
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

        $status = Password::broker('einladung')->reset(
            $request->only('username', 'password', 'password_confirmation', 'token'),
            function ($nutzer) use ($request) {
                $nutzer->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($nutzer));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => __($status)]);
        }

        // Bewusst nicht direkt anmelden: Wer ab jetzt hereinkommt, soll den
        // Weg gehen, den er kuenftig immer geht - samt zweiter Stufe, falls
        // sein Administrator sie verlangt.
        return redirect()->route('login')->with('status', __('Kennwort gesetzt. Sie können sich jetzt anmelden.'));
    }
}
