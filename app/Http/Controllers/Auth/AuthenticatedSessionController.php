<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\RedirectsAfterLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use RedirectsAfterLogin;

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

        // regenerate() hands out a new id but keeps the data. A password hash
        // from an earlier sign-in in the same session would make
        // AuthenticateSession (see Kernel) sign the user out on the next call -
        // and that leads back here: a sign-in loop.
        $request->session()->regenerate();
        $request->session()->forget('password_hash_web');

        // Second factor switched on? Then the sign-in is not finished yet. The
        // user is signed out again - the session holds only who wants to get
        // in. Until the one-time code is right, they are nobody.
        if (auth()->user()->hasTwoFactor()) {
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
