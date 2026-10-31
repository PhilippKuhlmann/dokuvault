<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The throttle on revealing a password has bitten - see App\Support\PasswordReveal.
 *
 * The fields in the browser catch it and show the message at the field.
 * Through the interface it becomes a 429 with the same message.
 */
class TooManyPasswordReveals extends RuntimeException
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
