<?php

namespace App\Support;

use App\Models\Certificate;
use App\Models\Domain;
use Illuminate\Support\Str;
use Throwable;

/**
 * Brings a domain or certificate in line with what the internet says.
 *
 * Only what a check actually found is written. A failed lookup leaves the
 * stored values alone and says why in check_error - documentation that
 * empties itself because a DNS server timed out is worse than none.
 */
class AutoCheck
{
    public function __construct(
        private DomainCheck $domains,
        private CertificateCheck $certificates,
    ) {}

    public function checkDomain(Domain $domain): void
    {
        if (blank($domain->name)) {
            $this->finish($domain, __('Kein Domainname eingetragen.'));

            return;
        }

        try {
            $found = $this->domains->check($domain->name, explode(',', (string) $domain->dkim_selectors));
        } catch (Throwable $e) {
            report($e);
            $this->finish($domain, Str::limit($e->getMessage(), 250));

            return;
        }

        if ($found['expiry_date']) {
            $domain->expiry_date = $found['expiry_date'];
        }

        // Never into "registrar": the registry names the accredited
        // wholesaler (Key-Systems), the customer deals with the provider in
        // front of it (EWE). That one is typed in; the registry's goes beside.
        if ($found['registrar']) {
            $domain->registry_registrar = $found['registrar'];
        }

        if ($found['nameservers']) {
            $domain->nameserver1 = $found['nameservers'][0] ?? null;
            $domain->nameserver2 = $found['nameservers'][1] ?? null;

            // The zone answered, so a missing MX, SPF, DMARC or DKIM is real
            // and not a lookup that failed.
            $domain->mx = $found['mx'] ? Str::limit(implode(', ', $found['mx']), 250) : null;
            $domain->spf = $found['spf'];
            $domain->dmarc = $found['dmarc'];

            if (! $found['incomplete']) {
                $domain->dkim = $found['dkim'];
                $domain->ptr = $found['ptr'] ?: null;
            }
        }

        $this->finish($domain, match (true) {
            ! $found['nameservers'] => __('Keine Nameserver gefunden.'),
            $found['incomplete'] => __('DNS antwortet langsam – DKIM und Reverse DNS werden beim nächsten Lauf geprüft.'),
            ! $found['expiry_date'] && $found['rdap_error'] !== null => $found['rdap_error'],
            default => null,
        });
    }

    public function checkCertificate(Certificate $certificate): void
    {
        $host = trim((string) ($certificate->check_host ?: $certificate->common_name));

        if ($host === '' || str_contains($host, '*')) {
            $this->finish($certificate, __('Für die Prüfung einen Host angeben – bei einem Wildcard-Zertifikat z. B. www.example.com.'));

            return;
        }

        try {
            $found = $this->certificates->check($host, (int) ($certificate->check_port ?: 443));
        } catch (Throwable $e) {
            $this->finish($certificate, Str::limit($e->getMessage(), 250));

            return;
        }

        if (blank($certificate->common_name) && $found['common_name']) {
            $certificate->common_name = $found['common_name'];
        }

        foreach (['issuer', 'issued_date', 'expiry_date'] as $field) {
            if ($found[$field]) {
                $certificate->$field = $found[$field];
            }
        }

        $this->finish($certificate, null);
    }

    private function finish(Domain|Certificate $model, ?string $error): void
    {
        $model->checked_at = now();
        $model->check_error = $error;
        $model->save();
    }
}
