<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Generates an SSH key pair with ssh-keygen.
 *
 * Deliberately not rebuilt in PHP: the OpenSSH format of the private part is
 * a packed binary structure, and with a passphrase bcrypt-KDF and aes256-ctr
 * come on top. A key rebuilt here would be subtly wrong if anything - and
 * that only shows when it is refused at three in the morning. ssh-keygen is
 * on every server that carries this application.
 */
class SshKeyGenerator implements FieldGenerator
{
    /** RSA below 3072 counts as too short; 4096 is the usual default. */
    private const BITS = ['rsa' => '4096', 'ecdsa' => '521'];

    public function generate(array $form): array
    {
        $verfahren = $form['key_type'] ?? '';

        if (! array_key_exists($verfahren, config('custom.ssh_key_types'))) {
            throw new RuntimeException(__('Bitte zuerst ein Verfahren wählen.'));
        }

        $ordner = $this->createFolder();
        $pfad = $ordner.'/schluessel';

        try {
            $this->aufrufen($this->befehl($verfahren, $pfad, $form));

            return [
                'public_key' => trim((string) file_get_contents($pfad.'.pub')),
                'private_key' => (string) file_get_contents($pfad),
            ];
        } finally {
            // Even when ssh-keygen fails: a private key must not be left
            // lying in the temp directory.
            $this->prune($ordner);
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

        // The passphrase is briefly visible in the process list. There would
        // be no way around it: ssh-keygen takes it only through -N or
        // interactively. Whoever can read the application server anyway can
        // also read the database - it is in there as well.
        $befehl[] = '-N';
        $befehl[] = (string) ($form['password'] ?? '');

        return $befehl;
    }

    /**
     * The comment ends up at the end of the public key and is what one
     * recognises it by later in an authorized_keys.
     */
    private function kommentar(array $form): string
    {
        $benutzer = Str::slug((string) ($form['username'] ?? '')) ?: 'key';
        $wofuer = Str::slug((string) ($form['name'] ?? '')) ?: 'dokuvault';

        return $benutzer.'@'.$wofuer;
    }

    private function aufrufen(array $befehl): void
    {
        // No shell call: arguments go in as an array, so neither comment nor
        // passphrase can be read as special characters.
        $prozess = new Process($befehl);
        $prozess->setTimeout(60);
        $prozess->run();

        if (! $prozess->isSuccessful()) {
            throw new RuntimeException(
                trim($prozess->getErrorOutput()) ?: __('Der Schlüssel ließ sich nicht erzeugen.')
            );
        }
    }

    private function createFolder(): string
    {
        $ordner = sys_get_temp_dir().'/dokuvault-ssh-'.bin2hex(random_bytes(8));

        // 0700: the private key lies here for the moment of generation, and
        // ssh-keygen refuses permissions that are too wide anyway.
        if (! @mkdir($ordner, 0700) && ! is_dir($ordner)) {
            throw new RuntimeException(__('Der Schlüssel ließ sich nicht erzeugen.'));
        }

        return $ordner;
    }

    private function prune(string $ordner): void
    {
        foreach (glob($ordner.'/*') ?: [] as $datei) {
            @unlink($datei);
        }

        @rmdir($ordner);
    }
}
