<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     *
     * @return View
     */
    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @return RedirectResponse
     *
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'username' => ['required'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        // Ohne Adresse kann der Link nirgendwohin. Das ungeprueft an den
        // Broker zu geben hiess: eine 500er-Seite auf einer oeffentlichen
        // Route, weil die Benachrichtigung an null geht.
        $nutzer = User::where('username', $request->input('username'))->first();

        if ($nutzer && ! $nutzer->email) {
            return back()->withInput($request->only('username'))->withErrors([
                'username' => __('Für diesen Zugang ist keine E-Mail-Adresse hinterlegt. Ihr Administrator kann das Kennwort setzen.'),
            ]);
        }

        $status = Password::sendResetLink(
            $request->only('username')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('username'))
                        ->withErrors(['username' => __($status)]);
    }
}
