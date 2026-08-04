<x-app-layout :$customer>

    <x-sitetopmenu can="mailbox_create" />

    @forelse ($customer->mailboxes as $mailbox)
        <x-card>
            <x-slot:head>
                <x-show.header can="mailbox_update" editUrl="{{ route('mailbox.edit', [$customer, $mailbox]) }}">
                    {{ $mailbox->name }}
                </x-show.header>
            </x-slot>

            <x-slot:body>


                <x-minitablecard :title="__('Login')" :array="[
                    'E-Mail Adresse' => $mailbox->mailAdress,
                    'Benutzer' => $mailbox->username,
                    'Passwort' => $mailbox->password,
                ]" />

                <x-minitablecard title="{{ $mailbox->mailboxProvider?->name }} - Eingang" :array="[
                    'POP3-Server' => $mailbox->mailboxProvider?->pop3server,
                    'POP3-Port' => $mailbox->mailboxProvider?->pop3port,
                    'IMAP-Server' => $mailbox->mailboxProvider?->imapserver,
                    'IMAP-Port' => $mailbox->mailboxProvider?->imapport,
                ]" />

                <x-minitablecard title="{{ $mailbox->mailboxProvider?->name }} - Ausgang" :array="[
                    'SMP-Server' => $mailbox->mailboxProvider?->smtpserver,
                    'SMTP-Port' => $mailbox->mailboxProvider?->smtpport,
                ]" />




            </x-slot>
        </x-card>
    @empty
    <x-emptystate />
@endforelse


</x-app-layout>
