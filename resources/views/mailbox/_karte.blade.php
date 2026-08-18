{{-- Ein Postfach in der Liste. --}}
        <x-card>
            <x-slot:head>
                <x-show.header can="mailbox_update" editAction="$dispatch('objekt-bearbeiten', { typ: 'mailbox', id: {{ $eintrag->id }} })">
                    {{ $eintrag->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-minitablecard :title="__('Login')" :array="[
                    'E-Mail Adresse' => $eintrag->mailAdress,
                    'Benutzer' => $eintrag->username,
                    'Passwort' => $eintrag->password,
                ]" />

                <x-minitablecard title="{{ $eintrag->mailboxProvider?->name }} - Eingang" :array="[
                    'POP3-Server' => $eintrag->mailboxProvider?->pop3server,
                    'POP3-Port' => $eintrag->mailboxProvider?->pop3port,
                    'IMAP-Server' => $eintrag->mailboxProvider?->imapserver,
                    'IMAP-Port' => $eintrag->mailboxProvider?->imapport,
                ]" />

                <x-minitablecard title="{{ $eintrag->mailboxProvider?->name }} - Ausgang" :array="[
                    'SMP-Server' => $eintrag->mailboxProvider?->smtpserver,
                    'SMTP-Port' => $eintrag->mailboxProvider?->smtpport,
                ]" />




            </x-slot>
        </x-card>
    
