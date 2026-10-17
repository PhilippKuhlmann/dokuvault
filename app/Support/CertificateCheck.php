<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The certificate a host actually serves - issuer, subject and validity.
 *
 * The peer is not verified on purpose: an expired or self-signed certificate
 * is exactly the one worth documenting, and a verifying handshake would
 * refuse to show it.
 */
class CertificateCheck
{
    /**
     * @return array{common_name: ?string, issuer: ?string, issued_date: ?string, expiry_date: ?string}
     *
     * @throws RuntimeException when the host can't be reached or sends no certificate
     */
    public function check(string $host, int $port = 443): array
    {
        $cert = $this->peerCertificate($host, $port);

        $issuer = $cert['issuer'] ?? [];
        $organisation = $issuer['O'] ?? null;
        $issuerName = $issuer['CN'] ?? null;

        return [
            'common_name' => $cert['subject']['CN'] ?? null,
            // "Let's Encrypt (R11)" says more than either part alone.
            'issuer' => match (true) {
                $organisation && $issuerName && $organisation !== $issuerName => $organisation.' ('.$issuerName.')',
                default => $organisation ?? $issuerName,
            },
            'issued_date' => isset($cert['validFrom_time_t']) ? Carbon::createFromTimestamp($cert['validFrom_time_t'])->toDateString() : null,
            'expiry_date' => isset($cert['validTo_time_t']) ? Carbon::createFromTimestamp($cert['validTo_time_t'])->toDateString() : null,
        ];
    }

    /** The parsed certificate - overridden in tests. */
    protected function peerCertificate(string $host, int $port): array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client('ssl://'.$host.':'.$port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            throw new RuntimeException(self::connectionError($host, $port, $errno, $errstr));
        }

        $cert = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $parsed = $cert ? openssl_x509_parse($cert) : false;

        if (! $parsed) {
            throw new RuntimeException(__(':ziel liefert kein Zertifikat.', ['ziel' => $host.':'.$port]));
        }

        return $parsed;
    }

    /**
     * What went wrong, in words the customer understands.
     *
     * The socket error is the operating system's ("php_network_getaddresses:
     * getaddrinfo for mail.example.com failed: nodename nor servname provided")
     * and differs between Linux and macOS - so match on the parts both share.
     * Whatever is not recognised still names host and port, without the
     * technical text.
     */
    public static function connectionError(string $host, int $port, int $errno, string $errstr): string
    {
        $fehler = strtolower($errstr);
        $hat = fn (string ...$teile) => collect($teile)->contains(fn ($teil) => str_contains($fehler, $teil));
        $werte = ['host' => $host, 'port' => $port];

        return match (true) {
            $hat('getaddrinfo', 'not known', 'no address associated', 'name resolution') => __(':host ist im öffentlichen DNS nicht bekannt. Läuft der Dienst nur intern, unter „Prüfen an Host“ einen von außen erreichbaren Namen eintragen.', $werte),
            $hat('refused') => __(':host nimmt auf Port :port keine Verbindung an.', $werte),
            $hat('timed out', 'timeout') => __(':host antwortet auf Port :port nicht – vermutlich nur intern erreichbar oder durch eine Firewall gesperrt.', $werte),
            $hat('unreachable', 'no route') => __(':host ist von hier aus nicht erreichbar.', $werte),
            $errno === 0 || $hat('ssl', 'tls', 'handshake', 'crypto') => __(':host spricht auf Port :port kein TLS – ist der Port richtig?', $werte),
            default => __('Keine Verbindung zu :host auf Port :port.', $werte),
        };
    }
}
