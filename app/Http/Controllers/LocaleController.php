<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switch the language for the running session.
 *
 * Deliberately reachable without signing in: on the sign-in page there is no
 * user whose setting could apply, and on the demo the shared account is
 * locked. The permanent choice sits in the profile.
 */
class LocaleController extends Controller
{
    public function __construct()
    {
        // Kein auth-Middleware: siehe Klassenkommentar.
    }

    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('custom.locales', [])), 404);

        $request->session()->put('locale', $locale);

        return back();
    }
}
