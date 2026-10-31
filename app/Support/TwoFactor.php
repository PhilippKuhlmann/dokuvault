<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * The second factor at sign-in: a one-time code from an authenticator app
 * (TOTP, RFC 6238).
 *
 * The QR code is generated here, not fetched from a service. Sending a secret
 * to someone else's server just so it can paint a picture from it would be
 * exactly the opposite of what this factor is for.
 */
class TwoFactor
{
    /** Wie viele Wiederherstellungscodes es gibt. */
    public const CODES = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Checks a code. By default Google2FA allows a time window before and
     * after - clocks drift apart, and typing six digits takes seconds.
     */
    public function stimmt(string $geheimnis, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($geheimnis, trim($code));
    }

    /**
     * Like stimmt(), but only accepts a code from a later time step than the
     * last accepted one.
     *
     * @return int|false the time step of the code, to be stored by the caller
     */
    public function newStep(string $geheimnis, string $code, ?int $letzter): int|false
    {
        return $this->google2fa->verifyKeyNewer($geheimnis, trim($code), $letzter ?? 0);
    }

    /**
     * The URL inside the QR code. The name before the colon shows up in the
     * app as a heading - hence the application name and not the bare
     * identifier.
     */
    public function url(User $nutzer, string $geheimnis): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $nutzer->username,
            $geheimnis
        );
    }

    /**
     * The QR code as SVG, ready to embed. No <?xml preamble, or it would
     * stand in the middle of the page.
     */
    public function qrCode(string $adresse): string
    {
        $bild = (new Writer(new ImageRenderer(
            new RendererStyle(200, 0),
            new SvgImageBackEnd
        )))->writeString($adresse);

        return trim(preg_replace('/^<\?xml.*?\?>/', '', $bild));
    }

    /**
     * Recovery codes. Whoever loses their phone would otherwise never get
     * back in - and this application holds the passwords of entire customer
     * networks.
     */
    public function recoveryCodes(): array
    {
        return collect()->times(self::CODES, fn () => Str::random(10).'-'.Str::random(10))->all();
    }
}
