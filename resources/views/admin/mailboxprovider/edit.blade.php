<x-admin-layout>
    <x-create.main :header="__('Mail Anbieter bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('admin.mailboxprovider.update', $mailboxprovider) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$mailboxprovider->name" />

        <x-create.doublerow14 :label1="__('POP3-Server')" name1="pop3server" :default1="$mailboxprovider->pop3server" :label2="__('Port')" name2="pop3port" type2="number" :default2="$mailboxprovider->pop3port" />

        <x-create.doublerow14 :label1="__('IMAP-Server')" name1="imapserver" :default1="$mailboxprovider->imapserver" :label2="__('Port')" name2="imapport" type2="number" :default2="$mailboxprovider->imapport" />

        <x-create.doublerow14 :label1="__('SMTP-Server')" name1="smtpserver" :default1="$mailboxprovider->smtpserver" :label2="__('Port')" name2="smtpport" type2="number" :default2="$mailboxprovider->smtpport" />

    </x-create.main>
</x-admin-layout>
