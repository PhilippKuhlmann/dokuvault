<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\LeitetNachAnmeldungWeiter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use LeitetNachAnmeldungWeiter;

    /**
     * Display the login view.
     *
     * @return View
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @return RedirectResponse
     */
    public function store(LoginRequest $request)
    {

        $request->authenticate();

        // regenerate() vergibt eine neue Kennung, behaelt aber die Daten. Ein
        // Kennwort-Hash aus einer frueheren Anmeldung in derselben Sitzung
        // wuerde AuthenticateSession (siehe Kernel) beim naechsten Aufruf zum
        // Abmelden bringen - und der fuehrt wieder hierher: eine Anmeldeschleife.
        $request->session()->regenerate();
        $request->session()->forget('password_hash_web');

        // Zweite Stufe eingeschaltet? Dann ist die Anmeldung noch nicht fertig.
        // Der Nutzer wird wieder abgemeldet - in der Sitzung steht nur, wer
        // hereinmoechte. Bis der Einmalcode stimmt, ist er niemand.
        if (auth()->user()->hatZweiteStufe()) {
            $id = auth()->id();

            Auth::guard('web')->logout();

            $request->session()->put(TwoFactorChallengeController::WARTET, $id);
            $request->session()->put(TwoFactorChallengeController::GEMERKT, $request->boolean('remember'));

            return redirect()->route('two-factor.login');
        }

        return $this->nachDerAnmeldung();
    }

    /**
     * Destroy an authenticated session.
     *
     * @return RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
