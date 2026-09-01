<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        // Siehe User::istDemoGeschuetzt(): auf der Demo wuerde eine
        // Passwortaenderung alle uebrigen Besucher aussperren.
        if ($request->user()->istDemoGeschuetzt()) {
            return back()->withErrors(
                ['current_password' => 'Das Passwort eines Demo-Zugangs lässt sich nicht ändern.'],
                'updatePassword'
            );
        }

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Alle uebrigen Sitzungen dieses Nutzers werden hierdurch ungueltig:
        // AuthenticateSession (siehe Kernel, Gruppe "web") haelt in jeder
        // Sitzung den Kennwort-Hash fest und wirft sie raus, sobald er nicht
        // mehr stimmt. Die eigene Sitzung zieht die Middleware am Ende dieser
        // Anfrage nach. Ein eigenes "Angemeldet bleiben"-Cookie verfaellt dabei
        // ebenfalls - es traegt denselben Hash.
        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
