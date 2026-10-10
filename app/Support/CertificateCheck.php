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
            throw new RuntimeException(__('Keine Verbindung zu :ziel: :fehler', ['ziel' => $host.':'.$port, 'fehler' => $errstr ?: $errno]));
        }

        $cert = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $parsed = $cert ? openssl_x509_parse($cert) : false;

        if (! $parsed) {
            throw new RuntimeException(__(':ziel liefert kein Zertifikat.', ['ziel' => $host.':'.$port]));
        }

        return $parsed;
    }
}
