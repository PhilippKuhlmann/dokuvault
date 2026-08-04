<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Siehe User::istDemoGeschuetzt(): Der Zugang ist auf der Demo
        // vollstaendig gesperrt, also auch hier - sonst haette ein Besucher
        // Namen, E-Mail und Sprache des geteilten Zugangs in der Hand.
        if ($request->user()->istDemoGeschuetzt()) {
            return Redirect::route('profile.edit')
                ->withErrors(['demo' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')]);
        }

        $request->user()->fill($request->validated());

        // Kein E-Mail-Bestaetigungsverfahren in diesem Projekt: User
        // implementiert MustVerifyEmail nicht, und users hat keine Spalte
        // email_verified_at. Der Breeze-Rest davon liess jede Aenderung der
        // E-Mail-Adresse in einen 500er laufen.

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Siehe User::istDemoGeschuetzt(): der Zugang muss der Demo erhalten bleiben.
        if ($request->user()->istDemoGeschuetzt()) {
            return back()->withErrors(
                ['password' => 'Ein Demo-Zugang lässt sich nicht löschen.'],
                'userDeletion'
            );
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current-password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
