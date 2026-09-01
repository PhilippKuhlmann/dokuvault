<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
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

        $user = auth()->user();

        // Nutzer mit fest zugeordnetem Kunden (Rolle "Kunde") -> direkt zum eigenen
        // Kunden-Dashboard. Alle anderen (Admin, Techniker, ...) haben keinen
        // festen Kunden und landen auf der Kundensuche/Übersicht (RouteServiceProvider::HOME),
        // von der aus auch die globale Suche erreichbar ist.
        if ($user->hasCustomer()) {
            return redirect()->intended('/'.$user->customer->slug);
        }

        return redirect()->intended(RouteServiceProvider::HOME);
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
