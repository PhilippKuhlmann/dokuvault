<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\Server;
use App\Models\Setting;
use App\Models\VM;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The remote-support button (x-remote.button) - via the server instead of
 * a link with the password in it.
 *
 * The RustDesk link carries the password in clear ("?password=..."); as an
 * href it sat in the HTML of every device list, readable without a click
 * and without a trace. Now the button points here: same right as the list,
 * the same brake as looking at a password, an entry in the log - and only
 * then the redirect to the remote tool.
 */
class RemoteConnectController extends Controller
{
    private const TYPES = ['server' => Server::class, 'vm' => VM::class, 'computer' => Computer::class];

    public function __invoke(string $type, int $id): RedirectResponse
    {
        $klasse = self::TYPES[$type] ?? abort(404);
        Gate::authorize('viewAny', $klasse);

        // A customer account only reaches its own devices (what isCustomer
        // does for the routes below /{customer}).
        $geraet = $klasse::findOrFail($id);
        abort_if(auth()->user()->hasCustomer() && (int) $geraet->customer_id !== (int) auth()->user()->customer_id, 403);
        $link = Setting::fernwartungsLink($geraet->remoteID, $geraet->remotePassword);
        abort_unless($link, 404);

        $schluessel = 'kennwort-ansehen:'.auth()->id();
        abort_if(RateLimiter::tooManyAttempts($schluessel, config('custom.kennwort.ansehen_je_minute')), 429);
        RateLimiter::hit($schluessel, 60);

        activity()
            ->event('fernwartung_verbunden')
            ->performedOn($geraet)
            ->causedBy(auth()->user())
            ->withProperties([
                'objekt' => $geraet->name,
                'attributes' => array_filter([
                    'Werkzeug' => Setting::fernwartung()['label'] ?? null,
                    'IP' => request()->ip(),
                ]),
            ])
            ->log('Fernwartung verbunden');

        return redirect()->away($link);
    }
}
