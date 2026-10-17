<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Die Bremse beim Kennwortabruf hat gegriffen - siehe App\Support\Kennwortabruf.
 *
 * Die Felder im Browser fangen sie und zeigen die Meldung am Feld. Ueber die
 * Schnittstelle wird daraus ein 429 mit derselben Meldung.
 */
class ZuVieleKennwortabrufe extends RuntimeException
{
    public function __construct(public readonly int $sekunden)
    {
        parent::__construct(__('Zu viele Kennwortabrufe in kurzer Zeit. Bitte einen Moment warten.'));
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 429, ['Retry-After' => $this->sekunden]);
    }
}
