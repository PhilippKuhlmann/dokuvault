<?php

namespace Database\Seeders;

use App\Models\MailboxProvider;
use Illuminate\Database\Seeder;

class MailboxProvidorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MailboxProvider::create([
            'name' => 'EWE',
            'pop3server' => 'pop.ewe.net',
            'pop3port' => '995',
            'imapserver' => 'imap.ewe.net',
            'imapport' => '993',
            'smtpserver' => 'smtp.ewe.net',
            'smtpport' => '587',
        ]);

        MailboxProvider::create([
            'name' => 'IONOS',
            'pop3server' => 'pop.ionos.de',
            'pop3port' => '995',
            'imapserver' => 'imap.ionos.de',
            'imapport' => '993',
            'smtpserver' => 'smtp.ionos.de',
            'smtpport' => '465',
        ]);

        MailboxProvider::create([
            'name' => 'STRATO',
            'pop3server' => 'pop3.strato.de',
            'pop3port' => '995',
            'imapserver' => 'imap.strato.de',
            'imapport' => '993',
            'smtpserver' => 'smtp.strato.de',
            'smtpport' => '465',
        ]);

        MailboxProvider::create([
            'name' => 'Experia',
            'pop3server' => 'mail.experia.eu',
            'pop3port' => '995',
            'imapserver' => 'mail.experia.eu',
            'imapport' => '993',
            'smtpserver' => 'smtp.experia.eu',
            'smtpport' => '465',
        ]);

        MailboxProvider::create([
            'name' => 'Hetzner',
            'pop3server' => 'mail.your-server.de',
            'pop3port' => '995',
            'imapserver' => 'mail.your-server.de',
            'imapport' => '993',
            'smtpserver' => 'mail.your-server.de',
            'smtpport' => '465',
        ]);

        MailboxProvider::create([
            'name' => 'Jimdo',
            'pop3server' => 'secure.emailsrvr.com',
            'pop3port' => '995',
            'imapserver' => 'secure.emailsrvr.com',
            'imapport' => '993',
            'smtpserver' => 'secure.emailsrvr.com',
            'smtpport' => '465',
        ]);

        MailboxProvider::create([
            'name' => 'Microsoft 365 / Exchange Online',
            'pop3server' => 'outlook.office365.com',
            'pop3port' => '995',
            'imapserver' => 'outlook.office365.com',
            'imapport' => '993',
            'smtpserver' => 'smtp.office365.com',
            'smtpport' => '587',
        ]);

        MailboxProvider::create([
            'name' => 'Google Workspace / Gmail',
            'pop3server' => 'pop.gmail.com',
            'pop3port' => '995',
            'imapserver' => 'imap.gmail.com',
            'imapport' => '993',
            'smtpserver' => 'smtp.gmail.com',
            'smtpport' => '587',
        ]);

        MailboxProvider::create([
            'name' => 'Telekom / T-Online',
            'pop3server' => 'securepop.t-online.de',
            'pop3port' => '995',
            'imapserver' => 'secureimap.t-online.de',
            'imapport' => '993',
            'smtpserver' => 'securesmtp.t-online.de',
            'smtpport' => '465',
        ]);

        MailboxProvider::create([
            'name' => 'GMX',
            'pop3server' => 'pop.gmx.net',
            'pop3port' => '995',
            'imapserver' => 'imap.gmx.net',
            'imapport' => '993',
            'smtpserver' => 'mail.gmx.net',
            'smtpport' => '587',
        ]);

        MailboxProvider::create([
            'name' => 'Web.de',
            'pop3server' => 'pop3.web.de',
            'pop3port' => '995',
            'imapserver' => 'imap.web.de',
            'imapport' => '993',
            'smtpserver' => 'smtp.web.de',
            'smtpport' => '587',
        ]);

        MailboxProvider::create([
            'name' => 'mailbox.org',
            'pop3server' => 'pop3.mailbox.org',
            'pop3port' => '995',
            'imapserver' => 'imap.mailbox.org',
            'imapport' => '993',
            'smtpserver' => 'smtp.mailbox.org',
            'smtpport' => '465',
        ]);

        MailboxProvider::create([
            'name' => 'Posteo',
            'pop3server' => 'posteo.de',
            'pop3port' => '995',
            'imapserver' => 'posteo.de',
            'imapport' => '993',
            'smtpserver' => 'posteo.de',
            'smtpport' => '465',
        ]);

        MailboxProvider::create([
            'name' => 'Vodafone / Arcor',
            'pop3server' => 'pop.vodafonemail.de',
            'pop3port' => '995',
            'imapserver' => 'imap.vodafonemail.de',
            'imapport' => '993',
            'smtpserver' => 'smtp.vodafonemail.de',
            'smtpport' => '587',
        ]);

    }
}
