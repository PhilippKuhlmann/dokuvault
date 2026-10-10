<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\Domain;
use App\Models\Setting;
use App\Support\AutoCheck;
use Illuminate\Console\Command;

/**
 * Checks every domain and certificate against the internet: expiry and DNS
 * of the domain, the certificate the host actually serves.
 *
 * Runs before expiry:notify, so the morning mail already has the new dates.
 * One entry failing (timeout, host gone) never stops the others - the reason
 * is stored on the entry and shown on its card.
 */
class CheckDomains extends Command
{
    protected $signature = 'domains:check
        {--dry-run : Only show what would be checked}
        {--force : Run even when the automatic check is switched off}';

    protected $description = 'Checks domains (RDAP, DNS) and certificates (TLS) and updates their entries';

    public function handle(AutoCheck $check): int
    {
        if (! Setting::autoCheck() && ! $this->option('force')) {
            $this->line('Automatic check is switched off (Admin -> Settings -> Agents).');

            return self::SUCCESS;
        }

        $failed = 0;

        Domain::where('auto_check', true)->orderBy('id')->each(function (Domain $domain) use ($check, &$failed) {
            if ($this->option('dry-run')) {
                $this->line('domain: '.$domain->name);

                return;
            }

            $check->checkDomain($domain);
            $failed += $domain->check_error ? 1 : 0;
            $this->line('domain: '.$domain->name.($domain->check_error ? ' - '.$domain->check_error : ''));
        });

        Certificate::where('auto_check', true)->orderBy('id')->each(function (Certificate $certificate) use ($check, &$failed) {
            if ($this->option('dry-run')) {
                $this->line('certificate: '.($certificate->check_host ?: $certificate->common_name ?: $certificate->name));

                return;
            }

            $check->checkCertificate($certificate);
            $failed += $certificate->check_error ? 1 : 0;
            $this->line('certificate: '.$certificate->name.($certificate->check_error ? ' - '.$certificate->check_error : ''));
        });

        // A failed lookup is a finding on the entry, not a failure of the
        // command - the scheduler should not report it as broken.
        if ($failed) {
            $this->warn($failed.' with a problem, see the entries.');
        }

        return self::SUCCESS;
    }
}
