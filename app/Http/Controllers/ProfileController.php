<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Notifications\EmailChanged;
use App\Support\ZweiteStufe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, ZweiteStufe $zweiteStufe): View
    {
        // Ein begonnenes, aber noch nicht bestaetigtes Geheimnis liegt in der
        // Sitzung - siehe TwoFactorController. Nur dann gibt es einen QR-Code
        // zu zeigen.
        $geheimnis = $request->session()->get(TwoFactorController::IN_ARBEIT);

        return view('profile.edit', [
            'user' => $request->user(),
            'zweiteStufeGeheimnis' => $geheimnis,
            'zweiteStufeQr' => $geheimnis
                ? $zweiteStufe->qrCode($zweiteStufe->adresse($request->user(), $geheimnis))
                : null,
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

        $user = $request->user();
        $oldEmail = $user->email;

        $user->fill(Arr::except($request->validated(), 'current_password'));

        // Kein E-Mail-Bestaetigungsverfahren in diesem Projekt: User
        // implementiert MustVerifyEmail nicht, und users hat keine Spalte
        // email_verified_at. Der Breeze-Rest davon liess jede Aenderung der
        // E-Mail-Adresse in einen 500er laufen.

        $user->save();

        // Only a real change of the reset target - not "Max@" to "max@".
        if (filled($oldEmail) && mb_strtolower($oldEmail) !== mb_strtolower((string) $user->email)) {
            $this->noticeToOldEmail($oldEmail, $user->name, (string) $user->email);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * The old address learns about the change. A mail server that is down
     * must not undo a change that is already saved - so it is only reported.
     */
    private function noticeToOldEmail(string $oldEmail, string $name, string $newEmail): void
    {
        try {
            Notification::route('mail', $oldEmail)->notify(new EmailChanged($name, $newEmail));
        } catch (Throwable $error) {
            report($error);
        }
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
