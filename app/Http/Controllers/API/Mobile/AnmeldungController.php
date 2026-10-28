<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MobileLoginRequest;
use App\Models\User;
use App\Support\ZweiteStufePruefung;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Anmelden und Abmelden aus der iOS-App.
 *
 * Die App hat keine Sitzung. Zwischen Kennwort und Einmalcode merkt sie sich
 * deshalb einen "Vorgang": wer sich anmelden will und bis wann - verschluesselt
 * mit dem Schluessel der Installation, damit niemand eine fremde Nummer
 * hineinschreibt. Bis der Code stimmt, gibt es kein Token.
 */
class AnmeldungController extends Controller
{
    public function __construct(private ZweiteStufePruefung $pruefung) {}

    public function anmelden(MobileLoginRequest $request): JsonResponse
    {
        $nutzer = $request->nutzerPruefen();
        $geraet = (string) $request->input('geraet');

        // Im Browser kaeme er nur bis zum Profil, um die zweite Stufe
        // einzurichten. Die App kann das nicht - also gar nicht erst hinein.
        if ($nutzer->mussZweiteStufeEinrichten()) {
            return response()->json([
                'message' => __('Ihr Administrator verlangt die zweite Stufe. Richten Sie sie zuerst im Browser in Ihrem Profil ein.'),
            ], 403);
        }

        if ($nutzer->hatZweiteStufe()) {
            return response()->json([
                'zweite_stufe' => true,
                'vorgang' => Crypt::encryptString(json_encode([
                    // Purpose: the key encrypts other values too; a ciphertext
                    // from elsewhere must not pass as a login step.
                    'zweck' => 'mobil-anmeldung',
                    'id' => $nutzer->id,
                    'bis' => now()->addMinutes(config('custom.mobil.zweite_stufe_minuten'))->timestamp,
                    'geraet' => $geraet,
                ])),
            ]);
        }

        return $this->tokenAusgeben($nutzer, $geraet);
    }

    public function zweiteStufe(Request $request): JsonResponse
    {
        $request->validate([
            'vorgang' => ['required', 'string'],
            'code' => ['required', 'string', 'max:64'],
        ]);

        $vorgang = $this->vorgang($request->input('vorgang'));
        $nutzer = User::find($vorgang['id']);

        if (! $nutzer || ! $nutzer->hatZweiteStufe()) {
            throw $this->abgelaufen();
        }

        // Vor der Codepruefung, wie im Browser: Sonst ist ein
        // Wiederherstellungscode schon verbraucht, wenn die Sperre auffaellt.
        if ($nutzer->istDeaktiviert()) {
            throw ValidationException::withMessages([
                'code' => __('Dieser Zugang ist deaktiviert. Wenden Sie sich an Ihre Administration.'),
            ]);
        }

        $this->pruefung->pruefen($nutzer, (string) $request->input('code'), $request->ip());

        // Ein Vorgang gibt genau ein Token. Ohne das liesse sich mit einem
        // mitgelesenen Vorgang und dem naechsten Code ein zweites holen.
        $verbraucht = 'mobil-vorgang:'.hash('sha256', $request->input('vorgang'));

        if (! Cache::add($verbraucht, true, now()->addMinutes(config('custom.mobil.zweite_stufe_minuten') + 1))) {
            throw $this->abgelaufen();
        }

        return $this->tokenAusgeben($nutzer, (string) $vorgang['geraet']);
    }

    /** Nur dieses Token - die anderen Geraete bleiben angemeldet. */
    public function abmelden(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function ich(Request $request): JsonResponse
    {
        return response()->json($this->nutzerDaten($request->user()));
    }

    private function tokenAusgeben(User $nutzer, string $geraet): JsonResponse
    {
        $ablauf = now()->addDays(config('custom.mobil.token_tage'));

        $token = $nutzer->createToken(trim('App '.$geraet), ['mobil'], $ablauf);

        // Dasselbe Ereignis wie im Browser: "Angemeldet" ins Protokoll und
        // "zuletzt angemeldet" am Benutzer, siehe AnmeldungProtokollieren.
        event(new Login('sanctum', $nutzer, false));

        return response()->json([
            'token' => $token->plainTextToken,
            'laeuft_ab' => $ablauf->toIso8601String(),
            'nutzer' => $this->nutzerDaten($nutzer),
        ]);
    }

    private function nutzerDaten(User $nutzer): array
    {
        $nutzer->loadMissing('customer');

        return [
            'name' => $nutzer->name,
            'username' => $nutzer->username,
            'kunde' => $nutzer->customer
                ? ['slug' => $nutzer->customer->slug, 'name' => $nutzer->customer->name]
                : null,
            'rechte' => [
                'kennwoerter' => Gate::forUser($nutzer)->allows('logingeneral_viewAny'),
                'schluessel' => Gate::forUser($nutzer)->allows('sshkey_viewAny'),
            ],
        ];
    }

    /** @return array{id: int, bis: int, geraet: string} */
    private function vorgang(string $verschluesselt): array
    {
        try {
            $vorgang = json_decode(Crypt::decryptString($verschluesselt), true);
        } catch (DecryptException) {
            throw $this->abgelaufen();
        }

        if (! is_array($vorgang) || ($vorgang['zweck'] ?? null) !== 'mobil-anmeldung' || ! isset($vorgang['id'], $vorgang['bis']) || $vorgang['bis'] < now()->timestamp) {
            throw $this->abgelaufen();
        }

        return $vorgang + ['geraet' => ''];
    }

    private function abgelaufen(): ValidationException
    {
        return ValidationException::withMessages([
            'vorgang' => __('Die Anmeldung ist abgelaufen. Bitte noch einmal mit Benutzername und Kennwort beginnen.'),
        ]);
    }
}
