<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Erzeugt ein SSH-Schluesselpaar mit ssh-keygen.
 *
 * Bewusst nicht in PHP nachgebaut: Das OpenSSH-Format des privaten Teils ist
 * eine gepackte Binaerstruktur, und mit Passphrase kommen bcrypt-KDF und
 * aes256-ctr dazu. Ein hier nachgebauter Schluessel waere im Zweifel subtil
 * falsch - und das faellt erst auf, wenn er nachts um drei nicht angenommen
 * wird. ssh-keygen liegt auf jedem Server, der diese Anwendung traegt.
 */
class SshKeyGenerator implements Erzeuger
{
    /** RSA unter 3072 gilt als zu kurz; 4096 ist die uebliche Vorgabe. */
    private const BITS = ['rsa' => '4096', 'ecdsa' => '521'];

    public function erzeugen(array $form): array
    {
        $verfahren = $form['key_type'] ?? '';

        if (! array_key_exists($verfahren, config('custom.ssh_key_types'))) {
            throw new RuntimeException(__('Bitte zuerst ein Verfahren wählen.'));
        }

        $ordner = $this->ordnerAnlegen();
        $pfad = $ordner.'/schluessel';

        try {
            $this->aufrufen($this->befehl($verfahren, $pfad, $form));

            return [
                'public_key' => trim((string) file_get_contents($pfad.'.pub')),
                'private_key' => (string) file_get_contents($pfad),
            ];
        } finally {
            // Auch wenn ssh-keygen scheitert: Ein privater Schluessel darf
            // nicht im Temp-Verzeichnis liegenbleiben.
            $this->aufraeumen($ordner);
        }
    }

    /**
     * @return array<int, string>
     */
    private function befehl(string $verfahren, string $pfad, array $form): array
    {
        $befehl = ['ssh-keygen', '-t', $verfahren, '-f', $pfad, '-q', '-C', $this->kommentar($form)];

        if (isset(self::BITS[$verfahren])) {
            $befehl[] = '-b';
            $befehl[] = self::BITS[$verfahren];
        }

        // Die Passphrase steht kurzzeitig in der Prozessliste. Ein eigener
        // Weg gaebe es nicht: ssh-keygen nimmt sie nur ueber -N oder
        // interaktiv entgegen. Wer den Anwendungsserver ohnehin einsehen kann,
        // kann auch die Datenbank lesen - dort steht sie ebenfalls.
        $befehl[] = '-N';
        $befehl[] = (string) ($form['password'] ?? '');

        return $befehl;
    }

    /**
     * Der Kommentar landet am Ende des oeffentlichen Schluessels und ist das,
     * woran man ihn spaeter in einer authorized_keys wiedererkennt.
     */
    private function kommentar(array $form): string
    {
        $benutzer = Str::slug((string) ($form['username'] ?? '')) ?: 'key';
        $wofuer = Str::slug((string) ($form['name'] ?? '')) ?: 'dokuvault';

        return $benutzer.'@'.$wofuer;
    }

    private function aufrufen(array $befehl): void
    {
        // Kein Shell-Aufruf: Argumente gehen als Feld hinein, damit weder
        // Kommentar noch Passphrase als Sonderzeichen gedeutet werden koennen.
        $prozess = new Process($befehl);
        $prozess->setTimeout(60);
        $prozess->run();

        if (! $prozess->isSuccessful()) {
            throw new RuntimeException(
                trim($prozess->getErrorOutput()) ?: __('Der Schlüssel ließ sich nicht erzeugen.')
            );
        }
    }

    private function ordnerAnlegen(): string
    {
        $ordner = sys_get_temp_dir().'/dokuvault-ssh-'.bin2hex(random_bytes(8));

        // 0700: Der private Schluessel liegt hier fuer den Moment der Erzeugung,
        // und ssh-keygen verweigert ohnehin zu weit gesetzte Rechte.
        if (! @mkdir($ordner, 0700) && ! is_dir($ordner)) {
            throw new RuntimeException(__('Der Schlüssel ließ sich nicht erzeugen.'));
        }

        return $ordner;
    }

    private function aufraeumen(string $ordner): void
    {
        foreach (glob($ordner.'/*') ?: [] as $datei) {
            @unlink($datei);
        }

        @rmdir($ordner);
    }
}
