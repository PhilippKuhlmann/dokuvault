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
 * Die zweite Stufe der Anmeldung: ein Einmalcode aus einer
 * Authentifizierungs-App (TOTP, RFC 6238).
 *
 * Der QR-Code wird hier erzeugt, nicht von einem Dienst geholt. Ein Geheimnis
 * an einen fremden Server zu schicken, nur damit er ein Bild daraus malt,
 * waere genau das Gegenteil dessen, was diese Stufe leisten soll.
 */
class ZweiteStufe
{
    /** Wie viele Wiederherstellungscodes es gibt. */
    public const CODES = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function geheimnisErzeugen(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Prueft einen Code. Google2FA laesst standardmaessig ein Zeitfenster
     * davor und danach gelten - Uhren gehen auseinander, und wer sechs Ziffern
     * abtippt, braucht dafuer Sekunden.
     */
    public function stimmt(string $geheimnis, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($geheimnis, trim($code));
    }

    /**
     * Die Adresse, die im QR-Code steht. Der Name vor dem Doppelpunkt
     * erscheint in der App als Ueberschrift - deshalb der Anwendungsname und
     * nicht die nackte Kennung.
     */
    public function adresse(User $nutzer, string $geheimnis): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $nutzer->username,
            $geheimnis
        );
    }

    /**
     * Der QR-Code als SVG, fertig zum Einbetten. Kein <?xml-Vorspann, sonst
     * steht er mitten in der Seite.
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
     * Wiederherstellungscodes. Wer sein Telefon verliert, kommt sonst nie
     * wieder herein - und in dieser Anwendung liegen die Kennwoerter ganzer
     * Kundennetze.
     */
    public function wiederherstellungscodes(): array
    {
        return collect()->times(self::CODES, fn () => Str::random(10).'-'.Str::random(10))->all();
    }
}
