<x-admin-layout>
    <x-create.main :header="__('Neuer Mailanbieter')" action="{{ route('admin.mailboxprovider.store') }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.doublerow14 :label1="__('POP3-Server')" name1="pop3server" :label2="__('Port')" name2="pop3port" type2="number" />

        <x-create.doublerow14 :label1="__('IMAP-Server')" name1="imapserver" :label2="__('Port')" name2="imapport" type2="number" />

        <x-create.doublerow14 :label1="__('SMTP-Server')" name1="smtpserver" :label2="__('Port')" name2="smtpport" type2="number" />

    </x-create.main>
</x-admin-layout>
